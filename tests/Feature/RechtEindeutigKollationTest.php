<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use Pdo\Sqlite;
use PHPUnit\Framework\Attributes\DataProvider;
use Transliterator;

/**
 * Der interne Name eines Einzelrechts ist auch dann eindeutig, wenn die Datenbank Akzente und Groß-/Kleinschreibung
 * ignoriert (MariaDB `utf8mb4_unicode_ci`: `cafe` = `café`). SQLite vergleicht byte-genau; deshalb legt dieser Test
 * die Spalte `permissions.permission` samt UNIQUE-Index mit einer eigenen Kollation `UNICODE_CI` neu an, die wie
 * MariaDB Akzente und Groß-/Kleinschreibung ignoriert. Index und `where`/`whereIn` vergleichen dann wie auf MariaDB.
 * (userauth-recht-eindeutig, Dashboard #386, Nachbesserung nach Review 1)
 *
 * Gruppe A (id 3) wird bearbeitet (Rechte `gruppe_a_a1`, `gruppe_a_a2`), Gruppe B (id 4) ist fremd.
 */
final class RechtEindeutigKollationTest extends TestCase
{
    use DatabaseMigrations;

    private const GRUPPE_A = 3;

    private int $a1;

    private int $a2;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        View::getFinder()->prependLocation(__DIR__.'/../Fixtures/adminviews');
        config(['laravelkit.theme' => 'tailwind']);

        $this->kollationEinrichten();

        $jetzt = now();
        foreach ([[1, 'dev', 'Dev'], [2, 'admin', 'Admin']] as [$id, $name, $anzeige]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => $anzeige, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }

        foreach ([[self::GRUPPE_A, 'gruppe_a', 'Gruppe A'], [4, 'gruppe_b', 'Gruppe B']] as [$id, $name, $anzeige]) {
            DB::table('permissions_groups')->insert([
                'id' => $id, 'group_name' => $name, 'group_display' => $anzeige, 'is_group' => 1,
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }

        $this->a1 = $this->recht('gruppe_a_a1', 'Recht A1', self::GRUPPE_A);
        $this->a2 = $this->recht('gruppe_a_a2', 'Recht A2', self::GRUPPE_A);
        $this->recht('user_create', 'Fremdes Recht', 4);
        $this->recht('cafe', 'Fremdes Cafe', 4);
        $this->recht('creme', 'Fremdes Creme', 4);
        $this->recht('cafe_geloescht', 'Gelöschtes Recht', 4, '2020-01-02 03:04:05');

        $this->dev = User::factory()->create(['role_id' => 2, 'name' => 'Dev Nutzer', 'email' => 'dev@example.test']);
    }

    /**
     * Registriert die Kollation UNICODE_CI an der Verbindung und legt `permissions` damit neu an
     * (die Tabelle ist an dieser Stelle leer; Fremdschlüssel sind während des Umbaus aus).
     */
    private function kollationEinrichten(): void
    {
        $pdo = DB::connection()->getPdo();
        $this->assertInstanceOf(Sqlite::class, $pdo);

        $falten = static function (string $text): string {
            $glatt = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower');

            return (string) $glatt?->transliterate($text);
        };
        $pdo->createCollation('UNICODE_CI', static fn (string $a, string $b): int => strcmp($falten($a), $falten($b)));

        $tabelle = (string) DB::scalar("select sql from sqlite_master where type = 'table' and name = 'permissions'");
        $indizes = DB::select("select sql from sqlite_master where type = 'index' and tbl_name = 'permissions' and sql is not null");
        $neu = (string) preg_replace('/"permission" varchar not null/', '"permission" varchar not null collate UNICODE_CI', $tabelle, 1, $ersetzt);
        $this->assertSame(1, $ersetzt, 'Die Spalte permissions.permission wurde nicht gefunden.');

        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('DROP TABLE "permissions"');
        $pdo->exec($neu);
        foreach ($indizes as $index) {
            $pdo->exec($index->sql);
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    private function recht(string $intern, string $anzeige, int $gruppe, ?string $geloescht = null): int
    {
        return DB::table('permissions')->insertGetId([
            'permission' => $intern, 'permission_display' => $anzeige, 'group_id' => $gruppe, 'crud' => 'single',
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $geloescht,
        ]);
    }

    /** Gegenprobe: der Aufbau greift, ein direkter Insert der Variante verletzt den UNIQUE-Index (und ändert nichts). */
    private function assertKollationGreift(string $variante): void
    {
        try {
            $this->recht($variante, 'Direkt', 4);
            $this->fail("Der direkte Insert von {$variante} hätte den UNIQUE-Index verletzen müssen.");
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }
    }

    /** Gegenprobe für zwei Zeilen: `$zweite` verletzt den UNIQUE-Index, sobald `$erste` gespeichert ist (wird zurückgerollt). */
    private function assertKollationGreiftFuerPaar(string $erste, string $zweite): void
    {
        DB::beginTransaction();
        try {
            $this->recht($erste, 'Direkt Eins', 4);
            $this->assertKollationGreift($zweite);
        } finally {
            DB::rollBack();
        }
    }

    /** @return array<string, mixed> */
    private function stand(): array
    {
        return [
            'gruppen' => DB::table('permissions_groups')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all(),
            'rechte' => DB::table('permissions')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all(),
        ];
    }

    /** @param array<string, mixed> $felder */
    private function store(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.create'))->post(
            route('permission.store'),
            array_merge(['group_display' => 'Neue Gruppe', 'group_name' => 'neue_gruppe', 'ckPermissionGroup' => 'on'], $felder)
        );
    }

    /** @param array<string, mixed> $felder */
    private function update(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.edit', self::GRUPPE_A))->post(
            route('permission.update', self::GRUPPE_A),
            array_merge(['group_display' => 'Gruppe A', 'group_name' => 'gruppe_a'], $felder)
        );
    }

    /** @return list<string> */
    private function neueRechteDerNeuenGruppe(): array
    {
        $id = (int) DB::table('permissions_groups')->where('group_display', 'Neue Gruppe')->value('id');

        return DB::table('permissions')->where('group_id', $id)->whereNull('deleted_at')->orderBy('id')->pluck('permission')->all();
    }

    /** @return array<string, array{0: string, 1: string}> [vorhandener Name, Variante, die die Datenbank als gleich ansieht] */
    public static function akzentVarianten(): array
    {
        return [
            'Akzent im Wort' => ['user_create', 'user_créate'],
            'Akzent am Ende' => ['cafe', 'café'],
            'Akzent und Großbuchstabe' => ['creme', 'CRÈME'],
        ];
    }

    // AK1
    #[DataProvider('akzentVarianten')]
    public function test_ak1_store_mit_akzent_variante_eines_vergebenen_namens_ergibt_fehler_am_feld(string $vorhanden, string $variante): void
    {
        $this->assertKollationGreift($variante);
        $vorher = $this->stand();

        $antwort = $this->store([
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => $variante,
            'permission_display_new_2' => 'Gültig Zwei', 'permission_new_2' => 'gueltig_zwei',
        ]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.create'));
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertFalse(session('errors')->has('permission_new_2'));
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: derselbe Aufbau speichert mit einem eindeutigen Namen
        $this->store(['permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'eindeutig_eins'])->assertRedirect(route('permission'));
        $this->assertSame(['eindeutig_eins'], $this->neueRechteDerNeuenGruppe());
    }

    // AK1
    public function test_ak1_store_mit_akzent_variante_aus_der_anzeige_ergibt_fehler_am_feld(): void
    {
        $this->assertKollationGreift('café');
        $vorher = $this->stand();

        $antwort = $this->store(['permission_display_new_1' => 'Café']);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertSame($vorher, $this->stand());

        $this->store(['permission_display_new_1' => 'Eindeutig'])->assertRedirect(route('permission'));
        $this->assertSame(['eindeutig'], $this->neueRechteDerNeuenGruppe());
    }

    // AK1
    #[DataProvider('akzentVarianten')]
    public function test_ak1_update_mit_neuem_recht_als_akzent_variante_ergibt_fehler_am_feld(string $vorhanden, string $variante): void
    {
        $this->assertKollationGreift($variante);
        $vorher = $this->stand();

        $antwort = $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_'.$this->a1 => 'Geändert A1',
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => $variante,
        ]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::GRUPPE_A));
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: dieselbe Anfrage mit eindeutigem Namen speichert Gruppe, Änderung und neues Recht
        $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_'.$this->a1 => 'Geändert A1',
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'eindeutig_eins',
        ])->assertRedirect(route('permission'));
        $this->assertSame('Geänderte Gruppe', DB::table('permissions_groups')->where('id', self::GRUPPE_A)->value('group_display'));
        $this->assertSame('Geändert A1', DB::table('permissions')->where('id', $this->a1)->value('permission_display'));
        $this->assertSame(1, DB::table('permissions')->where('permission', 'eindeutig_eins')->count());
    }

    // AK1
    public function test_ak1_update_mit_neuem_recht_als_akzent_variante_eines_rechts_der_eigenen_gruppe_ergibt_fehler_am_feld(): void
    {
        $this->assertKollationGreift('Gruppe_A_Á1');
        $vorher = $this->stand();

        $antwort = $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'Gruppe_A_Á1',
        ]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::GRUPPE_A));
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertSame($vorher, $this->stand());

        $this->update(['group_display' => 'Geänderte Gruppe', 'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'eindeutig_eins'])
            ->assertRedirect(route('permission'));
        $this->assertSame(1, DB::table('permissions')->where('permission', 'eindeutig_eins')->count());
    }

    // AK1
    public function test_ak1_soft_geloeschtes_recht_zaehlt_auch_als_akzent_variante_im_store(): void
    {
        $this->assertKollationGreift('café_geloescht');
        $vorher = $this->stand();

        $antwort = $this->store(['permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'café_geloescht']);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertSame($vorher, $this->stand());

        $this->store(['permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'eindeutig_eins'])->assertRedirect(route('permission'));
        $this->assertSame(['eindeutig_eins'], $this->neueRechteDerNeuenGruppe());
    }

    // AK1
    public function test_ak1_soft_geloeschtes_recht_zaehlt_auch_als_akzent_variante_im_update(): void
    {
        $this->assertKollationGreift('café_geloescht');
        $vorher = $this->stand();

        $antwort = $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'café_geloescht',
        ]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertSame($vorher, $this->stand());

        $this->update(['group_display' => 'Geänderte Gruppe', 'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'eindeutig_eins'])
            ->assertRedirect(route('permission'));
        $this->assertSame(1, DB::table('permissions')->where('permission', 'eindeutig_eins')->count());
    }

    // AK2
    #[DataProvider('akzentVarianten')]
    public function test_ak2_umbenennen_auf_akzent_variante_eines_vergebenen_namens_ergibt_fehler_am_feld(string $vorhanden, string $variante): void
    {
        $this->assertKollationGreift($variante);
        $vorher = $this->stand();

        $antwort = $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_'.$this->a1 => 'Umbenannt', 'permission_'.$this->a1 => $variante,
            'permission_display_'.$this->a2 => 'Geändert A2',
        ]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::GRUPPE_A));
        $antwort->assertSessionHasErrors('permission_'.$this->a1);
        $this->assertFalse(session('errors')->has('permission_'.$this->a2));
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: mit eindeutigem Namen wird umbenannt und alles gespeichert
        $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_'.$this->a1 => 'Umbenannt', 'permission_'.$this->a1 => 'eindeutig_eins',
            'permission_display_'.$this->a2 => 'Geändert A2',
        ])->assertRedirect(route('permission'));
        $this->assertSame('eindeutig_eins', DB::table('permissions')->where('id', $this->a1)->value('permission'));
        $this->assertSame('Geändert A2', DB::table('permissions')->where('id', $this->a2)->value('permission_display'));
    }

    // AK2
    public function test_ak2_eigener_name_in_anderer_schreibweise_ist_erlaubt(): void
    {
        $this->assertKollationGreift('Gruppe_A_Á1');

        $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_'.$this->a1 => 'Umbenannt', 'permission_'.$this->a1 => 'Gruppe_A_Á1',
        ])->assertRedirect(route('permission'))->assertSessionHasNoErrors();
        $this->assertSame('Umbenannt', DB::table('permissions')->where('id', $this->a1)->value('permission_display'));
    }

    // AK3
    #[DataProvider('akzentPaare')]
    public function test_ak3_store_zwei_zeilen_die_die_datenbank_als_gleich_ansieht_ergeben_fehler(string $erste, string $zweite): void
    {
        $this->assertKollationGreiftFuerPaar($erste, $zweite);
        $vorher = $this->stand();

        $antwort = $this->store([
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => $erste,
            'permission_display_new_2' => 'Anzeige Zwei', 'permission_new_2' => $zweite,
        ]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.create'));
        $this->assertTrue(session('errors')->has('permission_new_1') || session('errors')->has('permission_new_2'));
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: mit verschiedenen Namen speichert dieselbe Route beide Rechte
        $this->store([
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'eindeutig_eins',
            'permission_display_new_2' => 'Anzeige Zwei', 'permission_new_2' => 'eindeutig_zwei',
        ])->assertRedirect(route('permission'));
        $this->assertSame(['eindeutig_eins', 'eindeutig_zwei'], $this->neueRechteDerNeuenGruppe());
    }

    // AK3
    #[DataProvider('akzentPaare')]
    public function test_ak3_update_zwei_zeilen_die_die_datenbank_als_gleich_ansieht_ergeben_fehler(string $erste, string $zweite): void
    {
        $this->assertKollationGreiftFuerPaar($erste, $zweite);
        $vorher = $this->stand();

        $antwort = $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => $erste,
            'permission_display_new_2' => 'Anzeige Zwei', 'permission_new_2' => $zweite,
        ]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::GRUPPE_A));
        $this->assertTrue(session('errors')->has('permission_new_1') || session('errors')->has('permission_new_2'));
        $this->assertSame($vorher, $this->stand());

        $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'eindeutig_eins',
            'permission_display_new_2' => 'Anzeige Zwei', 'permission_new_2' => 'eindeutig_zwei',
        ])->assertRedirect(route('permission'));
        $this->assertSame(2, DB::table('permissions')->whereIn('permission', ['eindeutig_eins', 'eindeutig_zwei'])->count());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function akzentPaare(): array
    {
        return [
            'cafe und café' => ['zeile_cafe', 'zeile_café'],
            'Akzent zuerst' => ['zeile_café', 'zeile_cafe'],
            'Großbuchstabe mit Akzent' => ['zeile_cafe', 'ZEILE_CAFÉ'],
        ];
    }

    // Randfall 1
    public function test_randfall1_eindeutige_namen_werden_mit_der_kollation_weiter_gespeichert(): void
    {
        $this->assertKollationGreift('café');

        $this->store([
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'cafe_neu',
            'permission_display_new_2' => 'Anzeige Zwei', 'permission_new_2' => 'creme_neu',
        ])->assertRedirect(route('permission'))->assertSessionHasNoErrors();

        $this->assertSame(['cafe_neu', 'creme_neu'], $this->neueRechteDerNeuenGruppe());
    }
}
