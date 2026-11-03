<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Der interne Name eines Einzelrechts ist eindeutig (userauth-recht-eindeutig, Dashboard #386).
 * Gruppe A (id 3) ist die bearbeitete Gruppe (Rechte `gruppe_a_a1`, `gruppe_a_a2`), Gruppe B (id 4) eine fremde Gruppe
 * mit dem Recht `fremder_name` und dem soft-gelöschten Recht `geloeschter_name`. `permission.store` legt „Neue Gruppe“ an.
 */
final class RechtEindeutigTest extends TestCase
{
    use RefreshDatabase;

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
        $this->recht('fremder_name', 'Fremdes Recht', 4);
        $this->recht('geloeschter_name', 'Gelöschtes Recht', 4, '2020-01-02 03:04:05');

        $this->dev = User::factory()->create(['role_id' => 1, 'name' => 'Dev Nutzer', 'email' => 'dev@example.test']);
    }

    private function recht(string $intern, string $anzeige, int $gruppe, ?string $geloescht = null): int
    {
        return DB::table('permissions')->insertGetId([
            'permission' => $intern, 'permission_display' => $anzeige, 'group_id' => $gruppe, 'crud' => 'single',
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $geloescht,
        ]);
    }

    /** @return array<string, mixed> alle Zeilen beider Tabellen, auch soft-gelöschte */
    private function stand(): array
    {
        return [
            'gruppen' => DB::table('permissions_groups')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all(),
            'rechte' => DB::table('permissions')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $felder
     */
    private function store(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.create'))->post(route('permission.store'), $this->storeFelder($felder));
    }

    /**
     * @param  array<string, mixed>  $felder
     */
    private function storeFolgen(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.create'))->followingRedirects()->post(route('permission.store'), $this->storeFelder($felder));
    }

    /**
     * @param  array<string, mixed>  $felder
     * @return array<string, mixed>
     */
    private function storeFelder(array $felder): array
    {
        return array_merge(['group_display' => 'Neue Gruppe', 'group_name' => 'neue_gruppe', 'ckPermissionGroup' => 'on'], $felder);
    }

    /**
     * @param  array<string, mixed>  $felder
     */
    private function update(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.edit', self::GRUPPE_A))->post(route('permission.update', self::GRUPPE_A), $this->updateFelder($felder));
    }

    /**
     * @param  array<string, mixed>  $felder
     */
    private function updateFolgen(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.edit', self::GRUPPE_A))->followingRedirects()->post(route('permission.update', self::GRUPPE_A), $this->updateFelder($felder));
    }

    /**
     * @param  array<string, mixed>  $felder
     * @return array<string, mixed>
     */
    private function updateFelder(array $felder): array
    {
        return array_merge(['group_display' => 'Gruppe A', 'group_name' => 'gruppe_a'], $felder);
    }

    /** @return list<string> */
    private function neueRechteDerNeuenGruppe(): array
    {
        $id = (int) DB::table('permissions_groups')->where('group_display', 'Neue Gruppe')->value('id');

        return DB::table('permissions')->where('group_id', $id)->whereNull('deleted_at')->orderBy('id')->pluck('permission')->all();
    }

    /** @return array<string, string> Feldname => Wert aller Eingabefelder der Seite */
    private function eingaben(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        $werte = [];
        foreach ((new DOMXPath($dom))->query('//input[@name and not(@disabled)]') ?: [] as $feld) {
            $werte[$feld->getAttribute('name')] = $feld->getAttribute('value');
        }

        return $werte;
    }

    /** @return array<string, array{0: string, 1: string}> [interner Name, Schreibweise, die formatiert diesen Namen ergibt] */
    public static function vergebeneNamen(): array
    {
        return [
            'Recht einer anderen Gruppe' => ['fremder_name', 'Fremder Name'],
            'Recht einer anderen Gruppe, Großschreibung' => ['fremder_name', 'FREMDER NAME'],
            'soft-gelöschtes Recht' => ['geloeschter_name', 'Gelöschter Name'],
            'Recht derselben Gruppe' => ['gruppe_a_a1', 'Gruppe_A_A1'],
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function vergebeneNamenUndQuellen(): array
    {
        $faelle = [];
        foreach (self::vergebeneNamen() as $name => [$intern, $schreibweise]) {
            foreach (['gesendet', 'aus der Anzeige, Feld fehlt', 'aus der Anzeige, Feld leer'] as $quelle) {
                $faelle[$name.' / '.$quelle] = [$intern, $schreibweise, $quelle];
            }
        }

        return $faelle;
    }

    /**
     * @return array<string, string>
     */
    private function neueZeile(string $schreibweise, string $quelle, int $n = 1): array
    {
        return match ($quelle) {
            'gesendet' => ['permission_display_new_'.$n => 'Beliebige Anzeige '.$n, 'permission_new_'.$n => $schreibweise],
            'aus der Anzeige, Feld fehlt' => ['permission_display_new_'.$n => $schreibweise],
            default => ['permission_display_new_'.$n => $schreibweise, 'permission_new_'.$n => ''],
        };
    }

    // AK1
    #[DataProvider('vergebeneNamenUndQuellen')]
    public function test_ak1_store_mit_vergebenem_namen_ergibt_fehler_am_feld_und_speichert_nichts(string $intern, string $schreibweise, string $quelle): void
    {
        $vorher = $this->stand();

        $antwort = $this->store(array_merge(
            $this->neueZeile($schreibweise, $quelle),
            ['permission_display_new_2' => 'Gültig Zwei', 'permission_new_2' => 'gueltig_zwei']
        ));

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.create'));
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: derselbe Aufbau speichert, wenn die Namen frei sind
        $this->store(['permission_display_new_1' => 'Frei Eins', 'permission_display_new_2' => 'Gültig Zwei', 'permission_new_2' => 'gueltig_zwei'])
            ->assertRedirect(route('permission'));
        $this->assertSame(['frei_eins', 'gueltig_zwei'], $this->neueRechteDerNeuenGruppe());
    }

    // AK1
    #[DataProvider('vergebeneNamenUndQuellen')]
    public function test_ak1_update_mit_vergebenem_namen_ergibt_fehler_am_feld_und_speichert_nichts(string $intern, string $schreibweise, string $quelle): void
    {
        $vorher = $this->stand();

        $antwort = $this->update(array_merge(
            $this->neueZeile($schreibweise, $quelle),
            [
                'group_display' => 'Geänderte Gruppe',
                'permission_display_'.$this->a1 => 'Geändert A1',
                'delete_intern_'.$this->a2 => 'gruppe_a_a2',
                'permission_display_new_2' => 'Gültig Zwei', 'permission_new_2' => 'gueltig_zwei',
            ]
        ));

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::GRUPPE_A));
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: dieselbe Anfrage mit freiem Namen speichert Gruppe, Änderung, Löschung und neue Rechte
        $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_'.$this->a1 => 'Geändert A1',
            'delete_intern_'.$this->a2 => 'gruppe_a_a2',
            'permission_display_new_1' => 'Frei Eins',
            'permission_display_new_2' => 'Gültig Zwei', 'permission_new_2' => 'gueltig_zwei',
        ])->assertRedirect(route('permission'));
        $this->assertSame('Geänderte Gruppe', DB::table('permissions_groups')->where('id', self::GRUPPE_A)->value('group_display'));
        $this->assertSame('Geändert A1', DB::table('permissions')->where('id', $this->a1)->value('permission_display'));
        $this->assertNotNull(DB::table('permissions')->where('id', $this->a2)->value('deleted_at'));
        $this->assertSame(1, DB::table('permissions')->where('permission', 'frei_eins')->count());
    }

    // AK1
    public function test_ak1_fehler_steht_bei_der_zeile_mit_ihrer_nummer(): void
    {
        $antwort = $this->store([
            'permission_display_new_1' => 'Frei Eins',
            'permission_display_new_3' => 'Fremder Name',
        ]);

        $antwort->assertSessionHasErrors('permission_new_3');
        // Gegenprobe: die freie Zeile 1 hat keinen Fehler
        $this->assertFalse(session('errors')->has('permission_new_1'));
    }

    // AK1
    public function test_ak1_store_zeigt_die_fehlermeldung_in_der_seite(): void
    {
        $felder = ['permission_display_new_1' => 'Fremder Name'];

        $this->store($felder)->assertSessionHasErrors('permission_new_1');
        $meldung = session('errors')->first('permission_new_1');
        $this->assertNotSame('', $meldung);

        $this->storeFolgen($felder)->assertOk()->assertSee(e($meldung), false);
    }

    // AK1
    public function test_ak1_update_zeigt_die_fehlermeldung_in_der_seite(): void
    {
        $felder = ['permission_display_new_1' => 'Fremder Name'];

        $this->update($felder)->assertSessionHasErrors('permission_new_1');
        $meldung = session('errors')->first('permission_new_1');
        $this->assertNotSame('', $meldung);

        $this->updateFolgen($felder)->assertOk()->assertSee(e($meldung), false);
    }

    // AK1
    public function test_ak1_listen_statt_einzelwerten_ergeben_keinen_5xx_bis_in_die_folgeseite(): void
    {
        // Gegenprobe: der Aufbau erreicht den Controller (Eingabe mit vergebenem Namen wird abgelehnt)
        $this->store(['permission_display_new_1' => 'Fremder Name'])->assertSessionHasErrors('permission_new_1');
        $vorher = $this->stand();

        foreach ([
            ['permission_display_new_1' => ['Fremder Name', 'x'], 'permission_new_1' => ['fremder_name']],
            ['permission_display_new_1' => 'Fremder Name', 'permission_new_1' => ['fremder_name', 'x']],
            ['permission_'.$this->a1 => ['fremder_name', 'x']],
        ] as $felder) {
            $antwort = $this->update($felder);
            $this->assertLessThan(500, $antwort->getStatusCode());
            $this->assertLessThan(500, $this->updateFolgen($felder)->getStatusCode());
            $antwort = $this->store($felder);
            $this->assertLessThan(500, $antwort->getStatusCode());
            $this->assertLessThan(500, $this->storeFolgen($felder)->getStatusCode());
        }

        $this->assertSame(
            $vorher['rechte'],
            $this->stand()['rechte']
        );
    }

    // AK2
    #[DataProvider('vergebeneNamen')]
    public function test_ak2_umbenennen_auf_vergebenen_namen_ergibt_fehler_am_feld_und_speichert_nichts(string $intern, string $schreibweise): void
    {
        if ($intern === 'gruppe_a_a1') {
            // eigener Name ist erlaubt (eigener Test); hier das andere Recht der Gruppe
            $schreibweise = 'Gruppe_A_A2';
        }
        $vorher = $this->stand();

        $antwort = $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_'.$this->a1 => 'Geändert A1',
            'permission_'.$this->a1 => $schreibweise,
            'permission_display_new_1' => 'Gültig Zwei', 'permission_new_1' => 'gueltig_zwei',
        ]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::GRUPPE_A));
        $antwort->assertSessionHasErrors('permission_'.$this->a1);
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: mit freiem Namen wird dieselbe Anfrage gespeichert
        $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_'.$this->a1 => 'Geändert A1',
            'permission_'.$this->a1 => 'Frei Umbenannt',
            'permission_display_new_1' => 'Gültig Zwei', 'permission_new_1' => 'gueltig_zwei',
        ])->assertRedirect(route('permission'));
        $this->assertSame('frei_umbenannt', DB::table('permissions')->where('id', $this->a1)->value('permission'));
    }

    // AK2
    public function test_ak2_eigener_name_ist_erlaubt(): void
    {
        foreach (['gruppe_a_a1', 'Gruppe_A_A1'] as $schreibweise) {
            $this->update([
                'permission_display_'.$this->a1 => 'Neue Anzeige '.$schreibweise,
                'permission_'.$this->a1 => $schreibweise,
            ])->assertRedirect(route('permission'));

            $this->assertSame('Neue Anzeige '.$schreibweise, DB::table('permissions')->where('id', $this->a1)->value('permission_display'));
            $this->assertSame('gruppe_a_a1', DB::table('permissions')->where('id', $this->a1)->value('permission'));
        }
    }

    // AK2
    #[DataProvider('ohneNamen')]
    public function test_ak2_ohne_gesendeten_namen_bleibt_der_name_und_es_gibt_keinen_fehler(bool $leerGesendet): void
    {
        $intern = $leerGesendet ? ['permission_'.$this->a1 => ''] : [];

        $this->update(array_merge(['permission_display_'.$this->a1 => 'Neue Anzeige'], $intern))
            ->assertRedirect(route('permission'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Neue Anzeige', DB::table('permissions')->where('id', $this->a1)->value('permission_display'));
        $this->assertSame('gruppe_a_a1', DB::table('permissions')->where('id', $this->a1)->value('permission'));
    }

    /** @return array<string, array{0: bool}> */
    public static function ohneNamen(): array
    {
        return ['Feld fehlt' => [false], 'Feld leer' => [true]];
    }

    // AK2
    public function test_ak2_fehler_steht_beim_feld_des_umbenannten_rechts(): void
    {
        $this->update([
            'permission_display_'.$this->a1 => 'A1', 'permission_'.$this->a1 => 'frei_a1',
            'permission_display_'.$this->a2 => 'A2', 'permission_'.$this->a2 => 'fremder_name',
        ])->assertSessionHasErrors('permission_'.$this->a2);

        // Gegenprobe: das frei umbenannte Recht hat keinen Fehler
        $this->assertFalse(session('errors')->has('permission_'.$this->a1));
    }

    // AK2
    public function test_ak2_update_zeigt_die_fehlermeldung_in_der_seite(): void
    {
        $felder = ['permission_display_'.$this->a1 => 'A1', 'permission_'.$this->a1 => 'fremder_name'];

        $this->update($felder)->assertSessionHasErrors('permission_'.$this->a1);
        $meldung = session('errors')->first('permission_'.$this->a1);
        $this->assertNotSame('', $meldung);

        $this->updateFolgen($felder)->assertOk()->assertSee(e($meldung), false);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: list<string>}> [Felder, Felder, an denen der Fehler stehen darf]
     */
    public static function doppelteZeilen(): array
    {
        return [
            'beide gesendet, gleich' => [
                ['permission_display_new_1' => 'Eins', 'permission_new_1' => 'doppelt', 'permission_display_new_2' => 'Zwei', 'permission_new_2' => 'doppelt'],
                ['permission_new_1', 'permission_new_2'],
            ],
            'beide gesendet, nur formatiert gleich' => [
                ['permission_display_new_1' => 'Eins', 'permission_new_1' => 'Dop;pelt', 'permission_display_new_2' => 'Zwei', 'permission_new_2' => 'DOPPELT'],
                ['permission_new_1', 'permission_new_2'],
            ],
            'beide aus der Anzeige' => [
                ['permission_display_new_1' => 'Dop;pelt', 'permission_display_new_2' => 'Doppelt'],
                ['permission_new_1', 'permission_new_2'],
            ],
            'eine gesendet, eine aus der Anzeige' => [
                ['permission_display_new_1' => 'Eins', 'permission_new_1' => 'doppelt', 'permission_display_new_2' => 'Doppelt'],
                ['permission_new_1', 'permission_new_2'],
            ],
        ];
    }

    // AK3
    #[DataProvider('doppelteZeilen')]
    public function test_ak3_store_doppelter_name_in_derselben_anfrage_ergibt_fehler_und_speichert_nichts(array $felder, array $fehlerfelder): void
    {
        $vorher = $this->stand();

        $antwort = $this->store($felder);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.create'));
        $this->assertNotEmpty(array_filter($fehlerfelder, fn (string $f): bool => session('errors')?->has($f) === true));
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: mit verschiedenen Namen speichert dieselbe Route beide Rechte
        $this->store(['permission_display_new_1' => 'Eins', 'permission_display_new_2' => 'Zwei'])->assertRedirect(route('permission'));
        $this->assertSame(['eins', 'zwei'], $this->neueRechteDerNeuenGruppe());
    }

    // AK3
    #[DataProvider('doppelteZeilen')]
    public function test_ak3_update_doppelter_name_in_derselben_anfrage_ergibt_fehler_und_speichert_nichts(array $felder, array $fehlerfelder): void
    {
        $vorher = $this->stand();

        $antwort = $this->update(array_merge($felder, ['group_display' => 'Geänderte Gruppe']));

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::GRUPPE_A));
        $this->assertNotEmpty(array_filter($fehlerfelder, fn (string $f): bool => session('errors')?->has($f) === true));
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: mit verschiedenen Namen speichert dieselbe Route beide Rechte
        $this->update(['group_display' => 'Geänderte Gruppe', 'permission_display_new_1' => 'Eins', 'permission_display_new_2' => 'Zwei'])
            ->assertRedirect(route('permission'));
        $this->assertSame(2, DB::table('permissions')->whereIn('permission', ['eins', 'zwei'])->count());
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: list<string>}>
     */
    public static function doppelteMitBestehenden(): array
    {
        return [
            'neues Recht und umbenanntes Recht' => [
                ['permission_display_new_1' => 'Eins', 'permission_new_1' => 'zielname', 'permission_display_A1' => 'A1', 'permission_A1' => 'ZielName'],
                ['permission_new_1', 'permission_A1'],
            ],
            'zwei umbenannte Rechte' => [
                ['permission_display_A1' => 'A1', 'permission_A1' => 'zielname', 'permission_display_A2' => 'A2', 'permission_A2' => 'Ziel;Name'],
                ['permission_A1', 'permission_A2'],
            ],
            'neues Recht aus der Anzeige und umbenanntes Recht' => [
                ['permission_display_new_1' => 'Ziel;Name', 'permission_display_A2' => 'A2', 'permission_A2' => 'zielname'],
                ['permission_new_1', 'permission_A2'],
            ],
        ];
    }

    // AK3
    #[DataProvider('doppelteMitBestehenden')]
    public function test_ak3_update_doppelter_name_zwischen_neuem_und_umbenanntem_recht_ergibt_fehler_und_speichert_nichts(array $felder, array $fehlerfelder): void
    {
        $ersetzt = [];
        foreach ($felder as $name => $wert) {
            $ersetzt[str_replace(['A1', 'A2'], [(string) $this->a1, (string) $this->a2], $name)] = $wert;
        }
        $fehlerfelder = array_map(fn (string $f): string => str_replace(['A1', 'A2'], [(string) $this->a1, (string) $this->a2], $f), $fehlerfelder);
        $vorher = $this->stand();

        $antwort = $this->update($ersetzt);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $this->assertNotEmpty(array_filter($fehlerfelder, fn (string $f): bool => session('errors')?->has($f) === true));
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: die Anfrage ohne Doppelung (zweiter Name frei) speichert
        $this->update([
            'permission_display_new_1' => 'Eins', 'permission_new_1' => 'zielname',
            'permission_display_'.$this->a2 => 'A2', 'permission_'.$this->a2 => 'anderer_name',
        ])->assertRedirect(route('permission'));
        $this->assertSame(1, DB::table('permissions')->where('permission', 'zielname')->count());
        $this->assertSame('anderer_name', DB::table('permissions')->where('id', $this->a2)->value('permission'));
    }

    // AK4
    public function test_ak4_store_zeigt_nach_dem_fehler_die_eingegebenen_werte_wieder(): void
    {
        $felder = [
            'group_display' => 'Eingabe Gruppe', 'group_name' => 'eingabegruppe',
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'fremder_name',
            'permission_display_new_2' => 'Anzeige Zwei', 'permission_new_2' => 'zwei_x',
        ];

        $this->store($felder)->assertSessionHasErrors('permission_new_1');

        $werte = $this->eingaben($this->storeFolgen($felder)->assertOk()->getContent());
        foreach ($felder as $name => $erwartet) {
            $this->assertSame($erwartet, $werte[$name] ?? null, $name);
        }
    }

    // AK4
    public function test_ak4_update_zeigt_nach_dem_fehler_die_eingegebenen_werte_wieder(): void
    {
        $felder = [
            'group_display' => 'Eingabe Gruppe', 'group_name' => 'eingabegruppe',
            'permission_display_'.$this->a1 => 'Eingabe A1', 'permission_'.$this->a1 => 'fremder_name',
            'permission_display_'.$this->a2 => 'Eingabe A2', 'permission_'.$this->a2 => 'a2_neu',
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'zwei_x',
            'permission_display_new_2' => 'Anzeige Zwei', 'permission_new_2' => 'drei_x',
        ];

        $this->update($felder)->assertSessionHasErrors('permission_'.$this->a1);

        $werte = $this->eingaben($this->updateFolgen($felder)->assertOk()->getContent());
        foreach ($felder as $name => $erwartet) {
            $this->assertSame($erwartet, $werte[$name] ?? null, $name);
        }
    }

    // Randfall 1
    public function test_randfall1_store_mit_eindeutigen_namen_speichert_wie_bisher(): void
    {
        $this->store([
            'permission_display_new_1' => 'Frei Eins',
            'permission_display_new_2' => 'Anzeige Zwei', 'permission_new_2' => 'Zwei Gesendet',
        ])->assertRedirect(route('permission'))->assertSessionHasNoErrors();

        $this->assertSame(['frei_eins', 'zwei_gesendet'], $this->neueRechteDerNeuenGruppe());
    }

    // Randfall 1
    public function test_randfall1_update_mit_eindeutigen_namen_speichert_wie_bisher(): void
    {
        $this->update([
            'group_display' => 'Geänderte Gruppe',
            'permission_display_'.$this->a1 => 'Geändert A1', 'permission_'.$this->a1 => 'Neu A1',
            'delete_intern_'.$this->a2 => 'gruppe_a_a2',
            'permission_display_new_1' => 'Frei Eins',
            'permission_display_new_2' => 'Anzeige Zwei', 'permission_new_2' => 'Zwei Gesendet',
        ])->assertRedirect(route('permission'))->assertSessionHasNoErrors();

        $this->assertSame('Geänderte Gruppe', DB::table('permissions_groups')->where('id', self::GRUPPE_A)->value('group_display'));
        $this->assertSame(['Geändert A1', 'neu_a1'], [
            DB::table('permissions')->where('id', $this->a1)->value('permission_display'),
            DB::table('permissions')->where('id', $this->a1)->value('permission'),
        ]);
        $this->assertNotNull(DB::table('permissions')->where('id', $this->a2)->value('deleted_at'));
        $this->assertSame(1, DB::table('permissions')->where('permission', 'frei_eins')->count());
        $this->assertSame(1, DB::table('permissions')->where('permission', 'zwei_gesendet')->count());
    }
}
