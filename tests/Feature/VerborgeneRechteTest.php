<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Entities\PermissionGroup;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rechte verborgener Gruppen serverseitig schützen. Abgedeckt: AK1–AK4 und alle Randfälle
 * (userauth-verborgene-rechte, Dashboard #323).
 */
final class VerborgeneRechteTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    private const ROLLE_ZIEL = 4;

    /** @var array<string, int> */
    private array $rechteIds = [];

    private int $naechsteRechteId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');

        $jetzt = now();
        foreach ([[1, 'dev'], [2, 'admin'], [self::ROLLE_NORMAL, 'user'], [self::ROLLE_ZIEL, 'editor']] as [$id, $name]) {
            DB::table('roles')->insert([
                'id' => $id, 'role' => $name, 'role_display' => ucfirst($name),
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }

        $this->gruppe(1, 'user', 'Gruppe Nutzer', 0, ['create', 'read', 'edit', 'delete']);
        $this->gruppe(2, 'role', 'Gruppe Rollen', 0, ['create', 'read', 'edit', 'delete']);
        // verborgene Entwickler-Gruppe
        $this->gruppe(7, 'permission', 'Gruppe Entwickler', 1, ['single']);

        foreach (['role_create', 'role_read', 'role_edit'] as $recht) {
            DB::table('role_permission')->insert([
                'role_id' => self::ROLLE_NORMAL,
                'permission_id' => $this->rechteIds[$recht],
            ]);
        }
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
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
            $recht = $istGruppe === 1 ? $name.'_einzel' : $name.'_'.$art;
            $rechtId = $this->naechsteRechteId++;
            DB::table('permissions')->insert([
                'id' => $rechtId, 'permission' => $recht, 'permission_display' => 'Recht '.$recht,
                'group_id' => $id, 'crud' => $art, 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            $this->rechteIds[$recht] = $rechtId;
        }
    }

    private function nutzer(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_NORMAL]);
    }

    private function recht(string $name): int
    {
        return $this->rechteIds[$name];
    }

    /**
     * @return list<int>
     */
    private function rollenRechte(int $rolleId): array
    {
        $ids = DB::table('role_permission')->where('role_id', $rolleId)->pluck('permission_id')
            ->map(fn ($i): int => (int) $i)->all();
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<string>  $rechte
     */
    private function rolleHatRechte(int $rolleId, array $rechte): void
    {
        foreach ($rechte as $recht) {
            DB::table('role_permission')->insert(['role_id' => $rolleId, 'permission_id' => $this->recht($recht)]);
        }
    }

    /**
     * @param  list<string>  $rechte
     * @return list<int>
     */
    private function ids(array $rechte): array
    {
        $ids = array_map(fn (string $r): int => $this->recht($r), $rechte);
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<int>  $rechteIds
     * @return TestResponse<Response>
     */
    private function speichern(string $anzeige, array $rechteIds)
    {
        return $this->actingAs($this->nutzer())->post(route('role.store'), [
            'role_display' => $anzeige, 'role' => strtolower($anzeige), 'permission' => $rechteIds,
        ]);
    }

    /**
     * @param  list<int>  $rechteIds
     * @return TestResponse<Response>
     */
    private function aendern(string $anzeige, array $rechteIds)
    {
        return $this->actingAs($this->nutzer())->post(route('role.update', self::ROLLE_ZIEL), [
            'role_display' => $anzeige, 'role' => strtolower($anzeige), 'permission' => $rechteIds,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rolleZeile(int $rolleId): array
    {
        return (array) DB::table('roles')->where('id', $rolleId)->first();
    }

    /** Verbirgt zusätzlich zu `permission` die Gruppe `lizenz` ausschließlich über einen Eingriff in die Gruppen-Abfrage. */
    private function verbirgeLizenzGruppe(): void
    {
        $this->gruppe(8, 'lizenz', 'Gruppe Lizenzen', 0, ['create', 'read', 'edit', 'delete']);
        PermissionGroup::addGlobalScope('test-verborgen', function (Builder $query): void {
            $query->where('permissions_groups.group_name', '!=', 'lizenz');
        });
    }

    // AK1
    public function test_anlegen_mit_verborgenem_recht_scheitert_am_feld_und_speichert_nichts(): void
    {
        // Gegenprobe: Anlegen mit sichtbarem Recht funktioniert
        $this->speichern('Neu A', [$this->recht('user_read')])
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));
        $rollenVorher = DB::table('roles')->count();
        $zuordnungenVorher = DB::table('role_permission')->count();

        $this->speichern('Neu B', [$this->recht('permission_einzel')])
            ->assertSessionHasErrors(['permission.0']);

        $this->assertSame($rollenVorher, DB::table('roles')->count());
        $this->assertSame(0, DB::table('roles')->where('role_display', 'Neu B')->count());
        $this->assertSame($zuordnungenVorher, DB::table('role_permission')->count());
    }

    // AK1
    public function test_bearbeiten_mit_verborgenem_recht_scheitert_am_feld_und_aendert_nichts(): void
    {
        $this->rolleHatRechte(self::ROLLE_ZIEL, ['user_edit']);
        // Gegenprobe: Bearbeiten mit sichtbarem Recht greift (Vorher-Stand danach lesen)
        $this->aendern('Editor A', [$this->recht('user_read')])
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));
        $this->assertSame($this->ids(['user_read']), $this->rollenRechte(self::ROLLE_ZIEL));
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);
        $zuordnungenVorher = DB::table('role_permission')->count();

        $this->aendern('Editor B', [$this->recht('permission_einzel')])
            ->assertSessionHasErrors(['permission.0']);

        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
        $this->assertSame($this->ids(['user_read']), $this->rollenRechte(self::ROLLE_ZIEL));
        $this->assertSame($zuordnungenVorher, DB::table('role_permission')->count());
    }

    // AK1
    public function test_fehler_steht_am_index_des_verborgenen_rechts(): void
    {
        $this->speichern('Neu A', [$this->recht('user_read'), $this->recht('permission_einzel')])
            ->assertSessionHasErrors(['permission.1'])
            ->assertSessionDoesntHaveErrors(['permission.0']);
    }

    // AK1
    public function test_liste_statt_einzelwert_im_recht_scheitert_ohne_500_bis_in_die_folgeseite(): void
    {
        $nutzer = $this->nutzer();
        $daten = [
            'role_display' => 'Neu A', 'role' => 'neu a',
            'permission' => [[$this->recht('user_read'), $this->recht('user_edit')]],
        ];

        $this->actingAs($nutzer)->from(route('role.create'))->post(route('role.store'), $daten)
            ->assertSessionHasErrors(['permission.0']);
        $this->actingAs($nutzer)->from(route('role.create'))->followingRedirects()
            ->post(route('role.store'), $daten)->assertOk();
        $this->assertSame(0, DB::table('roles')->where('role_display', 'Neu A')->count());

        $this->actingAs($nutzer)->from(route('role.edit', self::ROLLE_ZIEL))
            ->post(route('role.update', self::ROLLE_ZIEL), $daten)
            ->assertSessionHasErrors(['permission.0']);
        $this->actingAs($nutzer)->from(route('role.edit', self::ROLLE_ZIEL))->followingRedirects()
            ->post(route('role.update', self::ROLLE_ZIEL), $daten)->assertOk();
    }

    // AK2
    public function test_anlegen_weist_sichtbare_rechte_zu_auch_wenn_verborgene_gruppe_existiert(): void
    {
        $this->speichern('Neu A', [$this->recht('user_read'), $this->recht('role_delete')])
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $rolleId = (int) DB::table('roles')->where('role_display', 'Neu A')->value('id');
        $this->assertNotSame(0, $rolleId);
        $this->assertSame($this->ids(['user_read', 'role_delete']), $this->rollenRechte($rolleId));
    }

    // AK2
    public function test_bearbeiten_weist_sichtbare_rechte_zu_auch_wenn_verborgene_gruppe_existiert(): void
    {
        $this->rolleHatRechte(self::ROLLE_ZIEL, ['user_create']);

        $this->aendern('Editor A', [$this->recht('user_read'), $this->recht('role_delete')])
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame($this->ids(['user_read', 'role_delete']), $this->rollenRechte(self::ROLLE_ZIEL));
        $this->assertSame('Editor A', $this->rolleZeile(self::ROLLE_ZIEL)['role_display']);
    }

    // AK3
    public function test_bearbeiten_behaelt_verborgenes_recht_und_setzt_genau_die_gesendeten_sichtbaren(): void
    {
        $this->rolleHatRechte(self::ROLLE_ZIEL, ['permission_einzel', 'user_read', 'user_edit']);

        $this->aendern('Editor A', [$this->recht('user_edit'), $this->recht('role_read')])
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame(
            $this->ids(['permission_einzel', 'user_edit', 'role_read']),
            $this->rollenRechte(self::ROLLE_ZIEL)
        );
        $this->assertNotContains($this->recht('user_read'), $this->rollenRechte(self::ROLLE_ZIEL));
    }

    // Randfall 1
    public function test_bearbeiten_ohne_feld_permission_entfernt_sichtbare_und_behaelt_verborgene(): void
    {
        $this->rolleHatRechte(self::ROLLE_ZIEL, ['permission_einzel', 'user_read', 'role_edit']);

        $this->actingAs($this->nutzer())->post(route('role.update', self::ROLLE_ZIEL), [
            'role_display' => 'Editor A', 'role' => 'editor a',
        ])->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame($this->ids(['permission_einzel']), $this->rollenRechte(self::ROLLE_ZIEL));
        $this->assertSame('Editor A', $this->rolleZeile(self::ROLLE_ZIEL)['role_display']);
    }

    // Randfall 1
    public function test_bearbeiten_mit_leerer_liste_entfernt_sichtbare_und_behaelt_verborgene(): void
    {
        $this->rolleHatRechte(self::ROLLE_ZIEL, ['permission_einzel', 'user_read', 'role_edit']);

        $this->actingAs($this->nutzer())->postJson(route('role.update', self::ROLLE_ZIEL), [
            'role_display' => 'Editor A', 'role' => 'editor a', 'permission' => [],
        ])->assertRedirect(route('role'));

        $this->assertSame($this->ids(['permission_einzel']), $this->rollenRechte(self::ROLLE_ZIEL));
        $this->assertSame('Editor A', $this->rolleZeile(self::ROLLE_ZIEL)['role_display']);
    }

    // Randfall 1
    public function test_bearbeiten_mit_leerem_wert_entfernt_sichtbare_und_behaelt_verborgene(): void
    {
        $this->rolleHatRechte(self::ROLLE_ZIEL, ['permission_einzel', 'user_read']);

        $this->actingAs($this->nutzer())->post(route('role.update', self::ROLLE_ZIEL), [
            'role_display' => 'Editor A', 'role' => 'editor a', 'permission' => '',
        ])->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame($this->ids(['permission_einzel']), $this->rollenRechte(self::ROLLE_ZIEL));
    }

    // Randfall 2
    public function test_gemischte_auswahl_beim_anlegen_wird_abgelehnt_und_nichts_gespeichert(): void
    {
        // Gegenprobe: rein sichtbare Auswahl wird angenommen
        $this->speichern('Neu A', [$this->recht('user_read'), $this->recht('user_edit')])
            ->assertSessionHasNoErrors();
        $rollenVorher = DB::table('roles')->count();
        $zuordnungenVorher = DB::table('role_permission')->count();

        $this->speichern('Neu B', [$this->recht('user_read'), $this->recht('permission_einzel')])
            ->assertSessionHasErrors(['permission.1']);

        $this->assertSame($rollenVorher, DB::table('roles')->count());
        $this->assertSame($zuordnungenVorher, DB::table('role_permission')->count());
    }

    // Randfall 2
    public function test_gemischte_auswahl_beim_bearbeiten_wird_abgelehnt_auch_sichtbare_nicht_gespeichert(): void
    {
        $this->rolleHatRechte(self::ROLLE_ZIEL, ['role_read']);
        // Gegenprobe: rein sichtbare Auswahl wird angenommen, danach Vorher-Stand
        $this->aendern('Editor A', [$this->recht('role_read'), $this->recht('role_edit')])
            ->assertSessionHasNoErrors();
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);
        $rechteVorher = $this->rollenRechte(self::ROLLE_ZIEL);
        $this->assertSame($this->ids(['role_read', 'role_edit']), $rechteVorher);

        $this->aendern('Editor B', [$this->recht('user_read'), $this->recht('permission_einzel')])
            ->assertSessionHasErrors(['permission.1']);

        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
        $this->assertSame($rechteVorher, $this->rollenRechte(self::ROLLE_ZIEL));
    }

    // Randfall 3
    public function test_soft_geloeschtes_recht_wird_beim_anlegen_abgelehnt(): void
    {
        // Gegenprobe: solange das Recht nicht gelöscht ist, wird es angenommen
        $this->speichern('Neu A', [$this->recht('user_delete')])->assertSessionHasNoErrors();
        $rollenVorher = DB::table('roles')->count();

        DB::table('permissions')->where('id', $this->recht('user_delete'))->update(['deleted_at' => now()]);

        $this->speichern('Neu B', [$this->recht('user_delete')])->assertSessionHasErrors(['permission.0']);
        $this->assertSame($rollenVorher, DB::table('roles')->count());
    }

    // Randfall 3
    public function test_soft_geloeschtes_recht_wird_beim_bearbeiten_abgelehnt(): void
    {
        $this->aendern('Editor A', [$this->recht('user_delete')])->assertSessionHasNoErrors();
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);

        DB::table('permissions')->where('id', $this->recht('user_delete'))->update(['deleted_at' => now()]);

        $this->aendern('Editor B', [$this->recht('user_delete')])->assertSessionHasErrors(['permission.0']);
        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
    }

    // AK4
    public function test_verborgene_gruppen_kommen_aus_dem_scope_beim_anlegen(): void
    {
        $this->verbirgeLizenzGruppe();

        // Gegenprobe: sichtbares Recht geht, Gruppe verborgen nur über den geänderten Scope-Eingriff
        $this->speichern('Neu A', [$this->recht('user_read')])->assertSessionHasNoErrors();
        $rollenVorher = DB::table('roles')->count();

        $this->speichern('Neu B', [$this->recht('lizenz_read')])->assertSessionHasErrors(['permission.0']);
        $this->assertSame($rollenVorher, DB::table('roles')->count());
        // und `permission` bleibt weiter verborgen
        $this->speichern('Neu C', [$this->recht('permission_einzel')])->assertSessionHasErrors(['permission.0']);
    }

    // AK4
    public function test_verborgene_gruppen_kommen_aus_dem_scope_beim_bearbeiten(): void
    {
        $this->verbirgeLizenzGruppe();
        $this->rolleHatRechte(self::ROLLE_ZIEL, ['user_read']);
        $this->aendern('Editor A', [$this->recht('user_edit')])->assertSessionHasNoErrors();
        $rechteVorher = $this->rollenRechte(self::ROLLE_ZIEL);
        $this->assertSame($this->ids(['user_edit']), $rechteVorher);

        $this->aendern('Editor B', [$this->recht('lizenz_read')])->assertSessionHasErrors(['permission.0']);

        $this->assertSame($rechteVorher, $this->rollenRechte(self::ROLLE_ZIEL));
        $this->assertSame('Editor A', $this->rolleZeile(self::ROLLE_ZIEL)['role_display']);
    }

    // AK4
    public function test_beim_bearbeiten_bleibt_recht_der_ueber_den_scope_verborgenen_gruppe_erhalten(): void
    {
        $this->verbirgeLizenzGruppe();
        $this->rolleHatRechte(self::ROLLE_ZIEL, ['lizenz_read', 'permission_einzel', 'user_read']);

        $this->aendern('Editor A', [$this->recht('role_read')])->assertSessionHasNoErrors();

        $this->assertSame(
            $this->ids(['lizenz_read', 'permission_einzel', 'role_read']),
            $this->rollenRechte(self::ROLLE_ZIEL)
        );
    }

    // AK4
    public function test_gruppenname_steht_nicht_in_request_und_controller_und_scope_wird_genutzt(): void
    {
        $basis = dirname(__DIR__, 2).'/src/Http/';
        $request = (string) file_get_contents($basis.'Requests/RolleSpeichernRequest.php');
        $controller = (string) file_get_contents($basis.'Controllers/RoleController.php');

        // Gegenprobe: die Dateien wurden gelesen
        $this->assertStringContainsString('RolleSpeichernRequest', $request);
        $this->assertStringContainsString('RoleController', $controller);

        foreach ([$request, $controller] as $quelltext) {
            $this->assertStringNotContainsString('group_name', $quelltext);
            $this->assertDoesNotMatchRegularExpression('/[\'"]permission[\'"]\s*(,|\))/', $quelltext);
            $this->assertDoesNotMatchRegularExpression('/permission[\'"]\s*[!=]=?\s*[\'"]|[\'"]permission[\'"]\s*[!=]=?\s*[\'"]/', $quelltext);
        }
        $this->assertStringContainsString('fuerRollenformular', $request);
        $this->assertStringContainsString('fuerRollenformular', $controller);
    }
}
