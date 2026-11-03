<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rollenformular validiert `role` und `role_display` immer (userauth-rolle-validierung, Dashboard #351).
 * Abgedeckt: AK1–AK4 und alle Randfälle.
 */
final class RolleValidierungTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NUTZER = 3;

    private const ROLLE_ZIEL = 4;

    private const ROLLE_ANDERE = 5;

    private const ROLLE_GELOESCHT = 6;

    /** @var array<string, int> */
    private array $rechteIds = [];

    private int $naechsteRechteId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');

        $jetzt = now();
        $rollen = [
            [1, 'dev', 'Dev', null],
            [2, 'admin', 'Admin', null],
            [self::ROLLE_NUTZER, 'user', 'User', null],
            [self::ROLLE_ZIEL, 'editor', 'Editor', null],
            [self::ROLLE_ANDERE, 'x', 'Rolle X', null],
            [self::ROLLE_GELOESCHT, 'geloescht', 'Geloeschte Rolle', $jetzt],
        ];
        foreach ($rollen as [$id, $schluessel, $anzeige, $geloescht]) {
            DB::table('roles')->insert([
                'id' => $id, 'role' => $schluessel, 'role_display' => $anzeige,
                'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => $geloescht,
            ]);
        }

        $this->gruppe(1, 'user');
        $this->gruppe(2, 'role');

        foreach (['role_create', 'role_read', 'role_edit'] as $recht) {
            DB::table('role_permission')->insert([
                'role_id' => self::ROLLE_NUTZER,
                'permission_id' => $this->rechteIds[$recht],
            ]);
        }
        // Ausgangsrechte der Zielrolle
        DB::table('role_permission')->insert([
            'role_id' => self::ROLLE_ZIEL, 'permission_id' => $this->rechteIds['user_edit'],
        ]);
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
    }

    private function gruppe(int $id, string $name): void
    {
        $jetzt = now();
        DB::table('permissions_groups')->insert([
            'id' => $id, 'group_name' => $name, 'group_display' => 'Gruppe '.$name, 'is_group' => 0,
            'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);

        foreach (['create', 'read', 'edit', 'delete'] as $art) {
            $recht = $name.'_'.$art;
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
        return User::factory()->create(['role_id' => self::ROLLE_NUTZER]);
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
     * @return array<string, mixed>
     */
    private function rolleZeile(int $rolleId): array
    {
        return (array) DB::table('roles')->where('id', $rolleId)->first();
    }

    /**
     * Gültige Daten der Zielrolle (eigener Schlüssel und Anzeigename); Rechte werden mitgesendet.
     *
     * @param  array<string, mixed>  $abweichung
     * @param  list<string>  $fehlt
     * @return array<string, mixed>
     */
    private function daten(array $abweichung = [], array $fehlt = []): array
    {
        $daten = array_merge([
            'role_display' => 'Editor',
            'role' => 'editor',
            'permission' => [$this->rechteIds['user_read']],
        ], $abweichung);

        foreach ($fehlt as $feld) {
            unset($daten[$feld]);
        }

        return $daten;
    }

    /**
     * @param  array<string, mixed>  $daten
     * @return TestResponse<Response>
     */
    private function aendern(array $daten)
    {
        return $this->actingAs($this->nutzer())->from(route('role.edit', self::ROLLE_ZIEL))
            ->post(route('role.update', self::ROLLE_ZIEL), $daten);
    }

    /**
     * @param  array<string, mixed>  $daten
     * @return TestResponse<Response>
     */
    private function anlegen(array $daten)
    {
        return $this->actingAs($this->nutzer())->from(route('role.create'))
            ->post(route('role.store'), $daten);
    }

    /** Beweist, dass der Aufbau greift: gültiges Bearbeiten wird gespeichert (Rechte danach = gesendet). */
    private function gegenprobeBearbeitenGreift(): void
    {
        $this->aendern($this->daten(['permission' => [$this->rechteIds['role_read']]]))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));
        $this->assertSame([$this->rechteIds['role_read']], $this->rollenRechte(self::ROLLE_ZIEL));
    }

    /** Gegenprobe für das Anlegen: eine gültige neue Rolle wird gespeichert. */
    private function gegenprobeAnlegenGreift(): void
    {
        $this->anlegen($this->daten(['role_display' => 'Neu A', 'role' => 'neu a']))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));
        $this->assertSame(1, DB::table('roles')->where('role', 'neu_a')->count());
    }

    // ------------------------------------------------------------------ AK1

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function pflichtfelder(): array
    {
        // [Feld, fehlt (sonst leer), Anzeigename ändert sich]
        return [
            'role fehlt, Anzeigename unverändert' => ['role', true, false],
            'role leer, Anzeigename unverändert' => ['role', false, false],
            'role fehlt, Anzeigename geändert' => ['role', true, true],
            'role leer, Anzeigename geändert' => ['role', false, true],
            'role_display fehlt' => ['role_display', true, false],
            'role_display leer' => ['role_display', false, false],
        ];
    }

    // AK1
    #[DataProvider('pflichtfelder')]
    public function test_bearbeiten_ohne_pflichtfeld_scheitert_am_feld_ohne_5xx(string $feld, bool $fehlt, bool $displayGeaendert): void
    {
        $this->gegenprobeBearbeitenGreift();

        $abweichung = $displayGeaendert ? ['role_display' => 'Editor Neu'] : [];
        $daten = $fehlt
            ? $this->daten($abweichung, [$feld])
            : $this->daten([...$abweichung, $feld => '']);

        $antwort = $this->aendern($daten);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors($feld);
        $andereFelder = array_values(array_diff(['role', 'role_display'], [$feld]));
        $antwort->assertSessionDoesntHaveErrors($andereFelder);
    }

    // AK1
    #[DataProvider('pflichtfelder')]
    public function test_bearbeiten_ohne_pflichtfeld_laesst_die_rolle_unveraendert(string $feld, bool $fehlt, bool $displayGeaendert): void
    {
        $this->gegenprobeBearbeitenGreift();
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);
        $rechteVorher = $this->rollenRechte(self::ROLLE_ZIEL);
        $rollenVorher = DB::table('roles')->count();

        $abweichung = $displayGeaendert
            ? ['role_display' => 'Editor Neu', 'permission' => [$this->rechteIds['role_edit']]]
            : ['permission' => [$this->rechteIds['role_edit']]];
        $daten = $fehlt
            ? $this->daten($abweichung, [$feld])
            : $this->daten([...$abweichung, $feld => '']);

        $this->aendern($daten);

        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
        $this->assertSame($rechteVorher, $this->rollenRechte(self::ROLLE_ZIEL));
        $this->assertSame($rollenVorher, DB::table('roles')->count());
    }

    // ------------------------------------------------------------------ AK2

    /**
     * @return array<string, array{0: string}>
     */
    public static function schluesselVarianten(): array
    {
        return [
            'Großbuchstabe' => ['X'],
            'gleich' => ['x'],
            'mit Semikolon, das das Formatieren entfernt' => ['x;'],
        ];
    }

    // AK2
    #[DataProvider('schluesselVarianten')]
    public function test_anlegen_mit_schluessel_einer_anderen_rolle_scheitert_am_feld_role(string $wert): void
    {
        $this->gegenprobeAnlegenGreift();
        $rollenVorher = DB::table('roles')->count();
        $zuordnungenVorher = DB::table('role_permission')->count();

        $this->anlegen($this->daten(['role_display' => 'Neu B', 'role' => $wert]))
            ->assertSessionHasErrors('role')
            ->assertSessionDoesntHaveErrors('role_display');

        $this->assertSame($rollenVorher, DB::table('roles')->count());
        $this->assertSame(0, DB::table('roles')->where('role_display', 'Neu B')->count());
        $this->assertSame($zuordnungenVorher, DB::table('role_permission')->count());
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function schluesselUndAnzeigename(): array
    {
        return [
            'X, Anzeigename unverändert' => ['X', false],
            'x, Anzeigename unverändert' => ['x', false],
            'x;, Anzeigename unverändert' => ['x;', false],
            'X, Anzeigename geändert' => ['X', true],
            'x, Anzeigename geändert' => ['x', true],
        ];
    }

    // AK2
    #[DataProvider('schluesselUndAnzeigename')]
    public function test_bearbeiten_mit_schluessel_einer_anderen_rolle_scheitert_am_feld_role(string $wert, bool $displayGeaendert): void
    {
        $this->gegenprobeBearbeitenGreift();

        $abweichung = ['role' => $wert, 'permission' => [$this->rechteIds['role_edit']]];
        if ($displayGeaendert) {
            $abweichung['role_display'] = 'Editor Neu';
        }

        $this->aendern($this->daten($abweichung))
            ->assertSessionHasErrors('role')
            ->assertSessionDoesntHaveErrors('role_display');
    }

    // AK2
    #[DataProvider('schluesselUndAnzeigename')]
    public function test_bearbeiten_mit_doppeltem_schluessel_speichert_nichts(string $wert, bool $displayGeaendert): void
    {
        $this->gegenprobeBearbeitenGreift();
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);
        $rechteVorher = $this->rollenRechte(self::ROLLE_ZIEL);

        $abweichung = ['role' => $wert, 'permission' => [$this->rechteIds['role_edit']]];
        if ($displayGeaendert) {
            $abweichung['role_display'] = 'Editor Neu';
        }
        $this->aendern($this->daten($abweichung));

        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
        $this->assertSame($rechteVorher, $this->rollenRechte(self::ROLLE_ZIEL));
        $this->assertSame('x', DB::table('roles')->where('id', self::ROLLE_ANDERE)->value('role'));
    }

    // ------------------------------------------------------------------ AK3

    // AK3
    public function test_anlegen_mit_anzeigename_einer_anderen_rolle_scheitert_am_feld_role_display(): void
    {
        $this->gegenprobeAnlegenGreift();
        $rollenVorher = DB::table('roles')->count();
        $zuordnungenVorher = DB::table('role_permission')->count();

        $this->anlegen($this->daten(['role_display' => 'Rolle X', 'role' => 'neu b']))
            ->assertSessionHasErrors('role_display')
            ->assertSessionDoesntHaveErrors('role');

        $this->assertSame($rollenVorher, DB::table('roles')->count());
        $this->assertSame(0, DB::table('roles')->where('role', 'neub')->count());
        $this->assertSame($zuordnungenVorher, DB::table('role_permission')->count());
    }

    // AK3
    public function test_bearbeiten_mit_anzeigename_einer_anderen_rolle_scheitert_am_feld_role_display(): void
    {
        $this->gegenprobeBearbeitenGreift();

        $this->aendern($this->daten(['role_display' => 'Rolle X']))
            ->assertSessionHasErrors('role_display')
            ->assertSessionDoesntHaveErrors('role');
    }

    // AK3
    public function test_bearbeiten_mit_doppeltem_anzeigenamen_speichert_nichts(): void
    {
        $this->gegenprobeBearbeitenGreift();
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);
        $rechteVorher = $this->rollenRechte(self::ROLLE_ZIEL);

        $this->aendern($this->daten(['role_display' => 'Rolle X', 'permission' => [$this->rechteIds['role_edit']]]));

        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
        $this->assertSame($rechteVorher, $this->rollenRechte(self::ROLLE_ZIEL));
        $this->assertSame('Rolle X', DB::table('roles')->where('id', self::ROLLE_ANDERE)->value('role_display'));
    }

    // ------------------------------------------------------------------ AK4

    // AK4
    public function test_bearbeiten_mit_eigenem_unveraendertem_schluessel_und_anzeigenamen_speichert_die_rechte(): void
    {
        $this->aendern($this->daten(['permission' => [$this->rechteIds['role_read'], $this->rechteIds['role_edit']]]))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame(
            [$this->rechteIds['role_read'], $this->rechteIds['role_edit']],
            $this->rollenRechte(self::ROLLE_ZIEL)
        );
        $zeile = $this->rolleZeile(self::ROLLE_ZIEL);
        $this->assertSame('editor', $zeile['role']);
        $this->assertSame('Editor', $zeile['role_display']);
    }

    // AK4
    public function test_bearbeiten_mit_eigenem_schluessel_in_anderer_schreibweise_wird_gespeichert(): void
    {
        $this->aendern($this->daten(['role' => 'EDITOR']))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame('editor', $this->rolleZeile(self::ROLLE_ZIEL)['role']);
    }

    // AK4
    public function test_bearbeiten_mit_neuem_freien_schluessel_und_anzeigenamen_wird_gespeichert(): void
    {
        $this->aendern($this->daten(['role_display' => 'Editor Neu', 'role' => 'Editor Neu']))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $zeile = $this->rolleZeile(self::ROLLE_ZIEL);
        $this->assertSame('editor_neu', $zeile['role']);
        $this->assertSame('Editor Neu', $zeile['role_display']);
    }

    // ------------------------------------------------------------------ Randfälle

    // Randfall 1
    public function test_anlegen_speichert_den_schluessel_formatiert(): void
    {
        $this->anlegen($this->daten(['role_display' => 'Neue Rolle', 'role' => 'Neue Rolle ä']))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame(1, DB::table('roles')->where('role', 'neue_rolle_ae')->count());
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function listenFelder(): array
    {
        // [Feld, Anlegen (sonst Bearbeiten)]
        return [
            'role beim Anlegen' => ['role', true],
            'role_display beim Anlegen' => ['role_display', true],
            'role beim Bearbeiten' => ['role', false],
            'role_display beim Bearbeiten' => ['role_display', false],
        ];
    }

    // Randfall 1
    #[DataProvider('listenFelder')]
    public function test_liste_statt_text_scheitert_am_feld_ohne_5xx(string $feld, bool $anlegen): void
    {
        $anlegen ? $this->gegenprobeAnlegenGreift() : $this->gegenprobeBearbeitenGreift();
        $liste = ['abc', 'def'];
        $daten = $anlegen
            ? $this->daten(['role_display' => 'Neu B', 'role' => 'neu b', $feld => $liste])
            : $this->daten([$feld => $liste]);

        $antwort = $anlegen ? $this->anlegen($daten) : $this->aendern($daten);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors($feld);
    }

    // Randfall 1
    #[DataProvider('listenFelder')]
    public function test_liste_statt_text_fuehrt_bis_in_die_folgeseite_ohne_5xx(string $feld, bool $anlegen): void
    {
        $anlegen ? $this->gegenprobeAnlegenGreift() : $this->gegenprobeBearbeitenGreift();
        $nutzer = $this->nutzer();
        $quelle = $anlegen ? route('role.create') : route('role.edit', self::ROLLE_ZIEL);
        $ziel = $anlegen ? route('role.store') : route('role.update', self::ROLLE_ZIEL);
        $daten = $anlegen
            ? $this->daten(['role_display' => 'Neu B', 'role' => 'neu b', $feld => ['abc', 'def']])
            : $this->daten([$feld => ['abc', 'def']]);

        $this->actingAs($nutzer)->from($quelle)->followingRedirects()->post($ziel, $daten)->assertOk();
    }

    // Randfall 1
    #[DataProvider('listenFelder')]
    public function test_liste_statt_text_speichert_nichts(string $feld, bool $anlegen): void
    {
        $anlegen ? $this->gegenprobeAnlegenGreift() : $this->gegenprobeBearbeitenGreift();
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);
        $rechteVorher = $this->rollenRechte(self::ROLLE_ZIEL);
        $rollenVorher = DB::table('roles')->count();
        $daten = $anlegen
            ? $this->daten(['role_display' => 'Neu B', 'role' => 'neu b', $feld => ['abc', 'def']])
            : $this->daten([$feld => ['abc', 'def'], 'permission' => [$this->rechteIds['role_edit']]]);

        $anlegen ? $this->anlegen($daten) : $this->aendern($daten);

        $this->assertSame($rollenVorher, DB::table('roles')->count());
        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
        $this->assertSame($rechteVorher, $this->rollenRechte(self::ROLLE_ZIEL));
    }

    // Randfall 2
    public function test_anlegen_mit_schluessel_einer_geloeschten_rolle_scheitert_am_feld_role(): void
    {
        $this->gegenprobeAnlegenGreift();
        $rollenVorher = DB::table('roles')->count();

        $this->anlegen($this->daten(['role_display' => 'Neu B', 'role' => 'Geloescht']))
            ->assertSessionHasErrors('role')
            ->assertSessionDoesntHaveErrors('role_display');

        $this->assertSame($rollenVorher, DB::table('roles')->count());
    }

    // Randfall 2
    public function test_anlegen_mit_anzeigename_einer_geloeschten_rolle_scheitert_am_feld_role_display(): void
    {
        $this->gegenprobeAnlegenGreift();
        $rollenVorher = DB::table('roles')->count();

        $this->anlegen($this->daten(['role_display' => 'Geloeschte Rolle', 'role' => 'neu b']))
            ->assertSessionHasErrors('role_display')
            ->assertSessionDoesntHaveErrors('role');

        $this->assertSame($rollenVorher, DB::table('roles')->count());
    }

    // Randfall 2
    public function test_bearbeiten_mit_schluessel_einer_geloeschten_rolle_scheitert_am_feld_role(): void
    {
        $this->gegenprobeBearbeitenGreift();
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);

        $this->aendern($this->daten(['role' => 'geloescht']))
            ->assertSessionHasErrors('role')
            ->assertSessionDoesntHaveErrors('role_display');

        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
    }

    // Randfall 2
    public function test_bearbeiten_mit_anzeigename_einer_geloeschten_rolle_scheitert_am_feld_role_display(): void
    {
        $this->gegenprobeBearbeitenGreift();
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);

        $this->aendern($this->daten(['role_display' => 'Geloeschte Rolle']))
            ->assertSessionHasErrors('role_display')
            ->assertSessionDoesntHaveErrors('role');

        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
    }

    // Randfall 3
    public function test_format_display_to_intern_wird_nur_an_einer_stelle_angewendet(): void
    {
        $basis = dirname(__DIR__, 2).'/src/Http/';
        $request = (string) file_get_contents($basis.'Requests/RolleSpeichernRequest.php');
        $controller = (string) file_get_contents($basis.'Controllers/RoleController.php');

        // Gegenprobe: die Dateien wurden gelesen
        $this->assertStringContainsString('RolleSpeichernRequest', $request);
        $this->assertStringContainsString('RoleController', $controller);

        $treffer = preg_match_all('/formatDisplayToIntern\s*\(/', $request.$controller);

        $this->assertSame(1, $treffer);
    }
}
