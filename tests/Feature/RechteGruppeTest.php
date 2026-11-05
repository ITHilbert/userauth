<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Das Rechteformular (`permission.update`) ändert und löscht nur Rechte der bearbeiteten Gruppe
 * (userauth-rechte-gruppe, Dashboard #374). Gruppe A (id 3) ist die bearbeitete Gruppe mit Einzelrechten,
 * Gruppe B (id 4) eine fremde Gruppe mit Einzelrechten, Gruppe C (id 5) eine fremde Gruppe mit CRUD-Rechten.
 */
final class RechteGruppeTest extends TestCase
{
    use RefreshDatabase;

    private const GRUPPE_A = 3;

    private const GELOESCHT_AM = '2020-01-02 03:04:05';

    /** @var array<string, int> */
    private array $rechte = [];

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        $jetzt = now();
        foreach ([[1, 'dev', 'Dev'], [2, 'admin', 'Admin']] as [$id, $name, $anzeige]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => $anzeige, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }

        $this->gruppe(self::GRUPPE_A, 'gruppe_a', 'Gruppe A', 1, ['a1', 'a2']);
        $this->gruppe(4, 'gruppe_b', 'Gruppe B', 1, ['b1', 'b2']);
        $this->gruppe(5, 'gruppe_c', 'Gruppe C', 0, ['read', 'edit']);

        $this->rechte['a_geloescht'] = DB::table('permissions')->insertGetId([
            'permission' => 'gruppe_a_alt', 'permission_display' => 'Altes Recht A', 'group_id' => self::GRUPPE_A,
            'crud' => 'single', 'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => self::GELOESCHT_AM,
        ]);

        $this->dev = User::factory()->create(['role_id' => 2, 'name' => 'Dev Nutzer', 'email' => 'dev@example.test']);
    }

    /**
     * @param  list<string>  $arten
     */
    private function gruppe(int $id, string $name, string $anzeige, int $istGruppe, array $arten): void
    {
        $jetzt = now();
        DB::table('permissions_groups')->insert([
            'id' => $id, 'group_name' => $name, 'group_display' => $anzeige, 'is_group' => $istGruppe,
            'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);

        foreach ($arten as $art) {
            $recht = $name.'_'.$art;
            $this->rechte[$recht] = DB::table('permissions')->insertGetId([
                'permission' => $recht, 'permission_display' => 'Recht '.$recht,
                'group_id' => $id, 'crud' => $istGruppe === 1 ? 'single' : $art, 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $felder
     */
    private function sendeFuerGruppeA(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->post(
            route('permission.update', self::GRUPPE_A),
            array_merge(['group_display' => 'Gruppe A', 'group_name' => 'gruppe_a'], $felder)
        );
    }

    /** @return array{permission: string, permission_display: string, deleted_at: ?string} */
    private function recht(int $id): array
    {
        $zeile = DB::table('permissions')->where('id', $id)->first(['permission', 'permission_display', 'deleted_at']);
        $this->assertNotNull($zeile, 'Recht '.$id.' existiert nicht mehr');

        return (array) $zeile;
    }

    /** @return array<string, array{0: string}> */
    public static function fremdeRechte(): array
    {
        return [
            'Einzelrecht einer anderen Gruppe' => ['gruppe_b_b1'],
            'CRUD-Recht einer anderen Gruppe' => ['gruppe_c_read'],
        ];
    }

    // AK1
    #[DataProvider('fremdeRechte')]
    public function test_ak1_recht_einer_anderen_gruppe_bleibt_unveraendert(string $fremd): void
    {
        $r = $this->rechte[$fremd];
        $vorher = $this->recht($r);

        $this->sendeFuerGruppeA([
            'permission_display_'.$r => 'Fremd umbenannt', 'permission_'.$r => 'fremd_neu',
        ])->assertRedirect(route('permission'));

        $this->assertSame($vorher, $this->recht($r));
    }

    // AK1
    public function test_ak1_rechte_der_eigenen_gruppe_werden_im_selben_post_wie_bisher_geaendert(): void
    {
        $r = $this->rechte['gruppe_b_b1'];
        $a = $this->rechte['gruppe_a_a1'];
        $vorherFremd = $this->recht($r);

        $this->sendeFuerGruppeA([
            'permission_display_'.$a => 'A1 geändert', 'permission_'.$a => 'a1_neu',
            'permission_display_'.$r => 'Fremd umbenannt', 'permission_'.$r => 'fremd_neu',
        ])->assertRedirect(route('permission'));

        $eigen = $this->recht($a);
        $this->assertSame('A1 geändert', $eigen['permission_display']);
        $this->assertSame('a1_neu', $eigen['permission']);
        $this->assertSame($vorherFremd, $this->recht($r));
    }

    // AK1
    public function test_ak1_eigenes_recht_wird_ohne_fremdes_recht_geaendert(): void
    {
        $a = $this->rechte['gruppe_a_a2'];

        $this->sendeFuerGruppeA([
            'permission_display_'.$a => 'A2 geändert', 'permission_'.$a => 'a2_neu',
        ])->assertRedirect(route('permission'));

        $this->assertSame('A2 geändert', $this->recht($a)['permission_display']);
        $this->assertSame('a2_neu', $this->recht($a)['permission']);
    }

    // AK2
    #[DataProvider('fremdeRechte')]
    public function test_ak2_recht_einer_anderen_gruppe_wird_nicht_geloescht(string $fremd): void
    {
        $r = $this->rechte[$fremd];
        $a = $this->rechte['gruppe_a_a1'];

        $this->sendeFuerGruppeA([
            'delete_intern_'.$a => 'on', 'delete_intern_'.$r => 'on',
        ])->assertRedirect(route('permission'));

        // Gegenprobe: das eigene Recht im selben POST wurde gelöscht
        $this->assertNotNull($this->recht($a)['deleted_at']);
        $this->assertNull($this->recht($r)['deleted_at']);
    }

    // AK2
    public function test_ak2_recht_der_eigenen_gruppe_wird_wie_bisher_soft_geloescht(): void
    {
        $a = $this->rechte['gruppe_a_a2'];
        $b = $this->rechte['gruppe_a_a1'];

        $this->sendeFuerGruppeA(['delete_intern_'.$a => 'on'])->assertRedirect(route('permission'));

        $this->assertNotNull($this->recht($a)['deleted_at'], 'Recht ist weiter als Zeile vorhanden, aber soft gelöscht');
        $this->assertNull($this->recht($b)['deleted_at']);
        $this->assertSame(1, DB::table('permissions')->where('id', $a)->count());
    }

    // AK3
    public function test_ak3_fehlender_interner_name_ergibt_keinen_5xx_und_aendert_die_anzeige(): void
    {
        $a = $this->rechte['gruppe_a_a1'];

        $antwort = $this->sendeFuerGruppeA(['permission_display_'.$a => 'Nur Anzeige']);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission'));
        $this->assertSame('Nur Anzeige', $this->recht($a)['permission_display']);
    }

    // AK3
    public function test_ak3_fehlender_interner_name_laesst_den_internen_namen_unveraendert(): void
    {
        $a = $this->rechte['gruppe_a_a1'];
        $b = $this->rechte['gruppe_a_a2'];

        $this->sendeFuerGruppeA([
            'permission_display_'.$a => 'Nur Anzeige',
            // Gegenprobe: ein anderes Recht derselben Gruppe mit internem Namen wird geändert
            'permission_display_'.$b => 'A2 geändert', 'permission_'.$b => 'a2_neu',
        ])->assertRedirect(route('permission'));

        $this->assertSame('gruppe_a_a1', $this->recht($a)['permission']);
        $this->assertSame('a2_neu', $this->recht($b)['permission']);
    }

    // AK3
    public function test_ak3_fehlender_interner_name_bei_fremdem_recht_ergibt_keinen_5xx_und_aendert_nichts(): void
    {
        $r = $this->rechte['gruppe_b_b1'];
        $a = $this->rechte['gruppe_a_a1'];
        $vorher = $this->recht($r);

        $antwort = $this->sendeFuerGruppeA([
            'permission_display_'.$r => 'Fremd umbenannt',
            'permission_display_'.$a => 'A1 geändert',
        ]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $this->assertSame('A1 geändert', $this->recht($a)['permission_display']);
        $this->assertSame($vorher, $this->recht($r));
    }

    // Randfall 1
    public function test_randfall1_unbekannte_id_wird_ignoriert_ohne_fehler(): void
    {
        $a = $this->rechte['gruppe_a_a1'];
        $anzahl = DB::table('permissions')->count();

        $antwort = $this->sendeFuerGruppeA([
            'permission_display_999999' => 'Gibt es nicht', 'permission_999999' => 'gibt_es_nicht',
            'delete_intern_999998' => 'on',
            'permission_display_'.$a => 'A1 geändert', 'permission_'.$a => 'a1_neu',
        ]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission'));
        // Gegenprobe: das bekannte Recht im selben POST wurde bearbeitet
        $this->assertSame('A1 geändert', $this->recht($a)['permission_display']);
        $this->assertSame($anzahl, DB::table('permissions')->count());
    }

    // AK3
    public function test_ak3_leerer_interner_name_ergibt_keinen_5xx_und_aendert_die_anzeige(): void
    {
        $a = $this->rechte['gruppe_a_a1'];

        $antwort = $this->sendeFuerGruppeA(['permission_display_'.$a => 'Nur Anzeige', 'permission_'.$a => '']);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission'));
        $this->assertSame('Nur Anzeige', $this->recht($a)['permission_display']);
    }

    // AK3
    public function test_ak3_leerer_interner_name_laesst_den_internen_namen_unveraendert(): void
    {
        $a = $this->rechte['gruppe_a_a1'];
        $b = $this->rechte['gruppe_a_a2'];

        $this->sendeFuerGruppeA([
            'permission_display_'.$a => 'Nur Anzeige', 'permission_'.$a => '',
            // Gegenprobe: ein anderes Recht derselben Gruppe mit internem Namen wird geändert
            'permission_display_'.$b => 'A2 geändert', 'permission_'.$b => 'a2_neu',
        ])->assertRedirect(route('permission'));

        $this->assertSame('gruppe_a_a1', $this->recht($a)['permission']);
        $this->assertSame('a2_neu', $this->recht($b)['permission']);
    }

    // Randfall 1
    public function test_randfall1_unbekannte_id_ohne_internen_namen_ergibt_keinen_5xx(): void
    {
        $antwort = $this->sendeFuerGruppeA(['permission_display_999999' => 'Gibt es nicht']);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission'));
    }

    // Randfall 2
    public function test_randfall2_soft_geloeschtes_recht_der_eigenen_gruppe_wird_nicht_geaendert(): void
    {
        $alt = $this->rechte['a_geloescht'];
        $a = $this->rechte['gruppe_a_a1'];
        $vorher = $this->recht($alt);

        $this->sendeFuerGruppeA([
            'permission_display_'.$alt => 'Wiederbelebt', 'permission_'.$alt => 'wiederbelebt',
            'permission_display_'.$a => 'A1 geändert', 'permission_'.$a => 'a1_neu',
        ])->assertRedirect(route('permission'));

        $this->assertSame('A1 geändert', $this->recht($a)['permission_display']);
        $this->assertSame($vorher, $this->recht($alt));
        $this->assertNotNull($this->recht($alt)['deleted_at']);
    }

    // Randfall 2
    public function test_randfall2_soft_geloeschtes_recht_der_eigenen_gruppe_wird_nicht_erneut_geloescht(): void
    {
        $alt = $this->rechte['a_geloescht'];
        $a = $this->rechte['gruppe_a_a2'];
        $vorher = $this->recht($alt);

        $this->sendeFuerGruppeA([
            'delete_intern_'.$alt => 'on', 'delete_intern_'.$a => 'on',
        ])->assertRedirect(route('permission'));

        $this->assertNotNull($this->recht($a)['deleted_at']);
        $this->assertSame($vorher, $this->recht($alt));
    }

    // Randfall 2
    public function test_randfall2_soft_geloeschtes_recht_wird_nicht_wiederbelebt(): void
    {
        $alt = $this->rechte['a_geloescht'];
        $a = $this->rechte['gruppe_a_a1'];

        $this->sendeFuerGruppeA([
            'permission_display_'.$alt => 'Wiederbelebt', 'permission_'.$alt => 'wiederbelebt',
            'permission_display_'.$a => 'A1 geändert', 'permission_'.$a => 'a1_neu',
        ])->assertRedirect(route('permission'));

        $this->assertSame('A1 geändert', $this->recht($a)['permission_display']);
        $this->assertNotNull($this->recht($alt)['deleted_at']);
    }
}
