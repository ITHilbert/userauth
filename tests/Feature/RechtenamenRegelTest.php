<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use ITHilbert\UserAuth\Traits\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eine Regel für interne Namen, leerer interner Name, README (userauth-rechtenamen, Dashboard #384).
 * Abgedeckt: AK1, AK3, AK7, Randfall 2. Die Beispieltabelle liegt in tests/Fixtures/rechtenamen.json
 * und gilt ebenso für den Browser (tests/js/rechtename.test.mjs, AK2).
 *
 * Aufbau: Gruppe 3 `alpha` ist eine CRUD-Gruppe, Gruppe 4 `einzel` eine Einzelrechte-Gruppe mit dem Recht
 * `einzel_lesen` (Anzeige „Einzel lesen“) und dem Recht `user.read`. Rolle 4 `editor` ist die Zielrolle.
 */
final class RechtenamenRegelTest extends TestCase
{
    use RefreshDatabase;

    private const ALPHA = 3;

    private const EINZEL = 4;

    private const ROLLE_ZIEL = 4;

    private User $dev;

    private int $rechtLesen;

    private int $rechtPunkt;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        View::getFinder()->prependLocation(__DIR__.'/../Fixtures/adminviews');
        config(['laravelkit.theme' => 'tailwind']);

        $jetzt = now();
        foreach ([[1, 'dev', 'Dev'], [2, 'admin', 'Admin'], [self::ROLLE_ZIEL, 'editor', 'Editor']] as [$id, $name, $anzeige]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => $anzeige, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }
        foreach ([[self::ALPHA, 'alpha', 'Alpha', 0], [self::EINZEL, 'einzel', 'Einzel', 1]] as [$id, $name, $anzeige, $istGruppe]) {
            DB::table('permissions_groups')->insert([
                'id' => $id, 'group_name' => $name, 'group_display' => $anzeige, 'is_group' => $istGruppe,
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
        foreach (['create', 'read', 'edit', 'delete'] as $art) {
            $this->recht('alpha_'.$art, 'Alpha '.$art, self::ALPHA, $art);
        }
        $this->rechtLesen = $this->recht('einzel_lesen', 'Einzel lesen', self::EINZEL, 'single');
        $this->rechtPunkt = $this->recht('user.read', 'User lesen', self::EINZEL, 'single');

        $this->dev = User::factory()->create(['role_id' => 1]);
    }

    private function recht(string $intern, string $anzeige, int $gruppe, string $crud): int
    {
        return DB::table('permissions')->insertGetId([
            'permission' => $intern, 'permission_display' => $anzeige, 'group_id' => $gruppe, 'crud' => $crud,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> alle Zeilen der Gruppen-, Rechte- und Rollentabelle, auch soft-gelöschte */
    private function stand(): array
    {
        $zeilen = fn (string $tabelle): array => DB::table($tabelle)->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all();

        return ['gruppen' => $zeilen('permissions_groups'), 'rechte' => $zeilen('permissions'), 'rollen' => $zeilen('roles')];
    }

    /** @return list<string> */
    private function rechteNamen(int $gruppe): array
    {
        return DB::table('permissions')->where('group_id', $gruppe)->whereNull('deleted_at')->orderBy('id')->pluck('permission')->all();
    }

    private function formatiert(string $anzeige): string
    {
        return (new class
        {
            use Format;
        })->formatDisplayToIntern($anzeige);
    }

    /** @return TestResponse<Response> */
    private function gruppeAnlegen(array $felder): TestResponse
    {
        $basis = ['group_display' => 'Neue Gruppe', 'group_name' => 'neuegruppe'];

        return $this->actingAs($this->dev)->from(route('permission.create'))->post(route('permission.store'), array_merge($basis, $felder));
    }

    /** @return TestResponse<Response> */
    private function gruppeAendern(int $gruppe, array $felder): TestResponse
    {
        $basis = $gruppe === self::ALPHA ? ['group_display' => 'Alpha', 'group_name' => 'alpha'] : ['group_display' => 'Einzel', 'group_name' => 'einzel'];

        return $this->actingAs($this->dev)->from(route('permission.edit', $gruppe))->post(route('permission.update', $gruppe), array_merge($basis, $felder));
    }

    /** @return TestResponse<Response> */
    private function rolleAnlegen(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('role.create'))->post(route('role.store'), array_merge(['role_display' => 'Neue Rolle', 'role' => 'neue_rolle'], $felder));
    }

    /** @return TestResponse<Response> */
    private function rolleAendern(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('role.edit', self::ROLLE_ZIEL))
            ->post(route('role.update', self::ROLLE_ZIEL), array_merge(['role_display' => 'Editor', 'role' => 'editor'], $felder));
    }

    // ---------------------------------------------------------------- Datenquellen

    /** @return array<string, array{0: string, 1: string}> */
    private static function tabelle(string $abschnitt): array
    {
        $json = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/rechtenamen.json'), true, 512, JSON_THROW_ON_ERROR);

        $faelle = [];
        foreach ($json[$abschnitt] as $zeile) {
            $faelle[json_encode($zeile['anzeige'], JSON_UNESCAPED_UNICODE)] = [$zeile['anzeige'], $zeile['intern']];
        }

        return $faelle;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function beispiele(): array
    {
        return self::tabelle('beispiele');
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function alleBeispiele(): array
    {
        return array_merge(self::tabelle('beispiele'), self::tabelle('weitere'));
    }

    /** @return array<string, array{0: string}> */
    public static function entfernteZeichen(): array
    {
        return [';' => [';'], ':' => [':'], '"' => ['"'], "'" => ["'"], '`' => ['`'], '~' => ['~'], '<' => ['<'], '>' => ['>'], '|' => ['|']];
    }

    /** @return array<string, array{0: string}> */
    public static function leereNamen(): array
    {
        return ['Doppelpunkt, Leerzeichen, Semikolon' => [': ;'], 'Gravis und Tilde' => ['`~'], 'Größer, Kleiner, Strich' => ['<|>'], 'Anführungszeichen' => ['"\'']];
    }

    // ---------------------------------------------------------------- AK1

    // AK1
    #[DataProvider('beispiele')]
    public function test_ak1_server_bildet_fuer_jedes_beispiel_den_namen_nach_der_regel(string $anzeige, string $erwartet): void
    {
        $this->assertSame($erwartet, $this->formatiert($anzeige));
    }

    // AK1
    #[DataProvider('alleBeispiele')]
    public function test_ak1_server_regel_gilt_fuer_weitere_eingaben_inklusive_leerraum_und_umlaut_reihenfolge(string $anzeige, string $erwartet): void
    {
        $this->assertSame($erwartet, $this->formatiert($anzeige));
    }

    // AK1
    #[DataProvider('entfernteZeichen')]
    public function test_ak1_server_entfernt_jedes_vorkommen_der_zeichen(string $zeichen): void
    {
        $this->assertSame('abc', $this->formatiert('a'.$zeichen.'b'.$zeichen.$zeichen.'c'));
        $this->assertSame('ab', $this->formatiert($zeichen.'a'.$zeichen.'b'.$zeichen));
        // Gegenprobe: andere Zeichen bleiben
        $this->assertSame('a-b.c', $this->formatiert('a-b.c'));
    }

    // AK1
    #[DataProvider('alleBeispiele')]
    public function test_ak1_server_zweimal_bilden_ist_einmal_bilden(string $anzeige, string $erwartet): void
    {
        $einmal = $this->formatiert($anzeige);
        $this->assertSame($erwartet, $einmal);
        $this->assertSame($einmal, $this->formatiert($einmal));
    }

    // AK1
    public function test_ak1_gruppe_anlegen_bildet_gruppen_und_rechtenamen_nach_der_regel(): void
    {
        $this->gruppeAnlegen(['group_display' => 'Bericht', 'group_name' => 'Bericht  Lesen   Alle'])->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $id = (int) DB::table('permissions_groups')->where('group_name', 'bericht_lesen_alle')->value('id');
        $this->assertGreaterThan(0, $id);
        $this->assertSame(
            ['bericht_lesen_alle_create', 'bericht_lesen_alle_read', 'bericht_lesen_alle_edit', 'bericht_lesen_alle_delete'],
            $this->rechteNamen($id)
        );
    }

    // AK1
    public function test_ak1_gruppe_aendern_bildet_gruppen_und_rechtenamen_nach_der_regel(): void
    {
        $this->gruppeAendern(self::ALPHA, ['group_name' => 'Export Ä'])->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $this->assertSame('export_ae', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
        $this->assertSame(['export_ae_create', 'export_ae_read', 'export_ae_edit', 'export_ae_delete'], $this->rechteNamen(self::ALPHA));
    }

    // AK1
    public function test_ak1_neues_einzelrecht_ohne_internen_namen_bildet_ihn_aus_der_anzeige_nach_der_regel(): void
    {
        $this->gruppeAendern(self::EINZEL, ['permission_display_new_1' => 'Größe : Ändern'])->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $this->assertSame(1, DB::table('permissions')->where('group_id', self::EINZEL)->where('permission', 'groesse_aendern')->count());
    }

    // AK1
    public function test_ak1_neues_einzelrecht_mit_internem_namen_bildet_ihn_nach_der_regel(): void
    {
        $this->gruppeAendern(self::EINZEL, ['permission_display_new_1' => 'Anzeige', 'permission_new_1' => 'Bericht  Lesen'])->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('permissions')->where('group_id', self::EINZEL)->where('permission', 'bericht_lesen')->count());
    }

    // AK1
    public function test_ak1_geaendertes_einzelrecht_bildet_den_internen_namen_nach_der_regel(): void
    {
        $this->gruppeAendern(self::EINZEL, [
            'permission_display_'.$this->rechtLesen => 'Einzel lesen',
            'permission_'.$this->rechtLesen => 'ÖL;Ü',
        ])->assertSessionHasNoErrors();

        $this->assertSame('oelue', DB::table('permissions')->where('id', $this->rechtLesen)->value('permission'));
    }

    // AK1
    public function test_ak1_rolle_anlegen_bildet_den_internen_namen_nach_der_regel(): void
    {
        $this->rolleAnlegen(['role_display' => 'Export', 'role' => 'Export Ä'])->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame(1, DB::table('roles')->where('role', 'export_ae')->count());
    }

    // AK1
    public function test_ak1_rolle_aendern_bildet_den_internen_namen_nach_der_regel(): void
    {
        $this->rolleAendern(['role' => 'Bericht  lesen   alle'])->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame('bericht_lesen_alle', DB::table('roles')->where('id', self::ROLLE_ZIEL)->value('role'));
    }

    // ---------------------------------------------------------------- Randfall 2

    // Randfall 2
    public function test_randfall2_gespeicherter_name_mit_punkt_bleibt_beim_speichern_der_gruppe_stehen(): void
    {
        $this->gruppeAendern(self::EINZEL, [
            'permission_display_'.$this->rechtPunkt => 'User lesen',
            'permission_'.$this->rechtPunkt => 'user.read',
        ])->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $this->assertSame('user.read', DB::table('permissions')->where('id', $this->rechtPunkt)->value('permission'));
    }

    // Randfall 2
    public function test_randfall2_sonderzeichen_bleiben_und_e_akut_wird_nur_kleingeschrieben(): void
    {
        $this->gruppeAendern(self::EINZEL, ['permission_display_new_1' => 'Kunde-Alt.2 É'])->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('permissions')->where('group_id', self::EINZEL)->where('permission', 'kunde-alt.2_é')->count());
    }

    // ---------------------------------------------------------------- AK3

    // AK3
    #[DataProvider('leereNamen')]
    public function test_ak3_neue_crud_gruppe_mit_leerem_internen_namen_ergibt_fehler_am_feld_und_speichert_nichts(string $name): void
    {
        $vorher = $this->stand();

        $antwort = $this->gruppeAnlegen(['group_name' => $name]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.create'));
        $antwort->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: derselbe Aufbau speichert mit gültigem Namen
        $this->gruppeAnlegen(['group_name' => 'Frei'])->assertSessionHasNoErrors()->assertRedirect(route('permission'));
        $this->assertSame(1, DB::table('permissions_groups')->where('group_name', 'frei')->count());
    }

    // AK3
    #[DataProvider('leereNamen')]
    public function test_ak3_neue_einzelrechte_gruppe_mit_leerem_internen_namen_ergibt_fehler_am_feld_und_speichert_nichts(string $name): void
    {
        $felder = ['ckPermissionGroup' => 'on', 'permission_display_new_1' => 'Neues Recht'];
        $vorher = $this->stand();

        $antwort = $this->gruppeAnlegen(['group_name' => $name] + $felder);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe
        $this->gruppeAnlegen(['group_name' => 'Frei'] + $felder)->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('permissions_groups')->where('group_name', 'frei')->where('is_group', 1)->count());
    }

    // AK3
    #[DataProvider('leereNamen')]
    public function test_ak3_geaenderte_crud_gruppe_mit_leerem_internen_namen_ergibt_fehler_am_feld_und_aendert_nichts(string $name): void
    {
        $vorher = $this->stand();

        $antwort = $this->gruppeAendern(self::ALPHA, ['group_name' => $name]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::ALPHA));
        $antwort->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe
        $this->gruppeAendern(self::ALPHA, ['group_name' => 'Gamma'])->assertSessionHasNoErrors()->assertRedirect(route('permission'));
        $this->assertSame('gamma', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
    }

    // AK3
    #[DataProvider('leereNamen')]
    public function test_ak3_geaenderte_einzelrechte_gruppe_mit_leerem_internen_namen_ergibt_fehler_am_feld_und_aendert_nichts(string $name): void
    {
        $vorher = $this->stand();

        $antwort = $this->gruppeAendern(self::EINZEL, ['group_name' => $name]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe
        $this->gruppeAendern(self::EINZEL, ['group_name' => 'Gamma'])->assertSessionHasNoErrors();
        $this->assertSame('gamma', DB::table('permissions_groups')->where('id', self::EINZEL)->value('group_name'));
    }

    // AK3
    public function test_ak3_fehler_am_gruppennamen_steht_in_der_seite(): void
    {
        $url = route('permission.edit', self::ALPHA);
        $felder = ['group_display' => 'Alpha', 'group_name' => ': ;'];

        $this->actingAs($this->dev)->from($url)->post(route('permission.update', self::ALPHA), $felder)->assertSessionHasErrors('group_name');
        $meldung = session('errors')->first('group_name');
        $this->assertNotSame('', $meldung);

        $this->actingAs($this->dev)->from($url)->followingRedirects()->post(route('permission.update', self::ALPHA), $felder)->assertSee(e($meldung), false);
    }

    // AK3
    #[DataProvider('leereNamen')]
    public function test_ak3_neues_einzelrecht_mit_leerer_anzeige_als_name_ergibt_fehler_am_feld_und_speichert_nichts(string $name): void
    {
        $felder = ['ckPermissionGroup' => 'on', 'group_name' => 'frei', 'permission_display_new_1' => $name];
        $vorher = $this->stand();

        $antwort = $this->gruppeAnlegen($felder);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: mit gültiger Anzeige speichert derselbe Aufbau
        $this->gruppeAnlegen(['permission_display_new_1' => 'Gültig'] + $felder)->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('permissions')->where('permission', 'gueltig')->count());
    }

    // AK3
    #[DataProvider('leereNamen')]
    public function test_ak3_neues_einzelrecht_in_bestehender_gruppe_mit_leerem_internen_namen_ergibt_fehler_am_feld(string $name): void
    {
        $vorher = $this->stand();

        $antwort = $this->gruppeAendern(self::EINZEL, ['permission_display_new_1' => 'Anzeige', 'permission_new_1' => $name]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe
        $this->gruppeAendern(self::EINZEL, ['permission_display_new_1' => 'Anzeige', 'permission_new_1' => 'frei_name'])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('permissions')->where('permission', 'frei_name')->count());
    }

    // AK3
    public function test_ak3_neues_einzelrecht_in_bestehender_gruppe_mit_leerer_anzeige_als_name_ergibt_fehler_am_feld(): void
    {
        $vorher = $this->stand();

        $antwort = $this->gruppeAendern(self::EINZEL, ['permission_display_new_1' => ': ;']);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe
        $this->gruppeAendern(self::EINZEL, ['permission_display_new_1' => 'Frei'])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('permissions')->where('permission', 'frei')->count());
    }

    // AK3
    #[DataProvider('leereNamen')]
    public function test_ak3_geaendertes_einzelrecht_mit_leerem_internen_namen_ergibt_fehler_am_feld_und_aendert_nichts(string $name): void
    {
        $felder = ['permission_display_'.$this->rechtLesen => 'Einzel lesen', 'permission_'.$this->rechtLesen => $name];
        $vorher = $this->stand();

        $antwort = $this->gruppeAendern(self::EINZEL, $felder);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('permission_'.$this->rechtLesen);
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: mit gültigem Namen ändert derselbe Aufbau
        $this->gruppeAendern(self::EINZEL, ['permission_'.$this->rechtLesen => 'einzel_neu'] + $felder)->assertSessionHasNoErrors();
        $this->assertSame('einzel_neu', DB::table('permissions')->where('id', $this->rechtLesen)->value('permission'));
    }

    // AK3
    public function test_ak3_rolle_mit_leerem_internen_namen_bleibt_ein_validierungsfehler_am_feld_role(): void
    {
        $vorher = $this->stand();

        $antwort = $this->rolleAnlegen(['role' => ': ;']);
        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('role');

        $antwort = $this->rolleAendern(['role' => ': ;']);
        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('role');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: gültige Namen werden gespeichert
        $this->rolleAnlegen(['role' => 'Neu A'])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('roles')->where('role', 'neu_a')->count());
    }

    // ---------------------------------------------------------------- AK7

    private function readme(): string
    {
        return (string) file_get_contents(__DIR__.'/../../README.md');
    }

    private function upgradeAbschnitt(): string
    {
        $readme = $this->readme();
        $start = strpos($readme, '## Upgrade-Hinweise (nach 3.0.0');
        $this->assertNotFalse($start, 'Abschnitt „Upgrade-Hinweise (nach 3.0.0“ fehlt');
        $ende = strpos($readme, "\n## ", $start + 5);

        return substr($readme, $start, $ende === false ? null : $ende - $start);
    }

    // AK7
    public function test_ak7_readme_nennt_die_regel_fuer_interne_namen_mit_beispielen(): void
    {
        $readme = $this->readme();

        $this->assertStringContainsString('bericht_lesen', $readme);
        $this->assertStringContainsString('export_ae', $readme);
        $this->assertStringContainsString('groesse_aendern', $readme);
    }

    // AK7
    public function test_ak7_upgrade_hinweis_nennt_das_neu_veroeffentlichen_der_assets(): void
    {
        $abschnitt = $this->upgradeAbschnitt();

        $this->assertMatchesRegularExpression('/Assets?.{0,120}veröffentlich|veröffentlich.{0,120}Assets?/su', $abschnitt);
        $this->assertStringContainsString('vendor:publish', $abschnitt);
    }

    // AK7
    public function test_ak7_upgrade_hinweis_nennt_das_neubilden_gespeicherter_namen_mit_grossen_umlauten(): void
    {
        $abschnitt = $this->upgradeAbschnitt();

        $this->assertMatchesRegularExpression('/(großen|grossen) Umlaut/u', $abschnitt);
        $this->assertMatchesRegularExpression('/(nächsten|naechsten) Speichern/u', $abschnitt);
    }
}
