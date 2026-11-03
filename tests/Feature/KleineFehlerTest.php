<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kleine Fehler (userauth-kleine-fehler, Dashboard #477, #483, #383).
 * Abgedeckt: AK1–AK5 und die Randfälle.
 *
 * Aufbau: Rolle 3 (Nutzer) hat die Rechte `role_create/_read/_edit`, Rolle 4 das Recht `zugang`, Rolle 1 ist `dev`.
 * Gruppe 3 `alpha` und Gruppe 4 `beta` sind CRUD-Gruppen mit je vier aktiven Rechten.
 */
final class KleineFehlerTest extends TestCase
{
    use RefreshDatabase;

    private const ALPHA = 3;

    private const BETA = 4;

    private const ROLLE_NUTZER = 3;

    private const ROLLE_ZUGANG = 4;

    private const GEHEIM = 'GEHEIMER-INHALT';

    /** @var array<string, int> */
    private array $rechteIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        View::getFinder()->prependLocation(__DIR__.'/../Fixtures/adminviews');
        config(['laravelkit.theme' => 'tailwind']);

        $jetzt = now();
        foreach ([[1, 'dev', 'Dev'], [2, 'admin', 'Admin'], [self::ROLLE_NUTZER, 'user', 'User'], [self::ROLLE_ZUGANG, 'zugang', 'Zugang']] as [$id, $name, $anzeige]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => $anzeige, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }

        $this->gruppe(1, 'role', 0);
        $this->gruppe(self::ALPHA, 'alpha', 0);
        $this->gruppe(self::BETA, 'beta', 0);
        $this->gruppe(5, 'zugangsgruppe', 1);

        foreach (['role_create', 'role_read', 'role_edit'] as $recht) {
            DB::table('role_permission')->insert(['role_id' => self::ROLLE_NUTZER, 'permission_id' => $this->rechteIds[$recht]]);
        }
        $this->rechteIds['zugang'] = $this->recht('zugang', 'Zugang', 5, 'single');
        DB::table('role_permission')->insert(['role_id' => self::ROLLE_ZUGANG, 'permission_id' => $this->rechteIds['zugang']]);

        Route::middleware(['web', 'hasPermission:zugang'])->get('/_t/zugang', fn () => self::GEHEIM);
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
    }

    private function gruppe(int $id, string $name, int $istGruppe): void
    {
        $jetzt = now();
        DB::table('permissions_groups')->insert([
            'id' => $id, 'group_name' => $name, 'group_display' => ucfirst($name), 'is_group' => $istGruppe,
            'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);

        if ($istGruppe === 0) {
            foreach (['create', 'read', 'edit', 'delete'] as $art) {
                $this->rechteIds[$name.'_'.$art] = $this->recht($name.'_'.$art, ucfirst($name).' '.$art, $id, $art);
            }
        }
    }

    private function recht(string $intern, string $anzeige, int $gruppe, string $crud, ?string $geloescht = null): int
    {
        return DB::table('permissions')->insertGetId([
            'permission' => $intern, 'permission_display' => $anzeige, 'group_id' => $gruppe, 'crud' => $crud,
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $geloescht,
        ]);
    }

    private function entwickler(): User
    {
        return User::factory()->create(['role_id' => 2]);
    }

    private function nutzer(int $rolle = self::ROLLE_NUTZER): User
    {
        return User::factory()->create(['role_id' => $rolle]);
    }

    /** @return array<string, mixed> alle Zeilen beider Gruppen-Tabellen, auch soft-gelöschte */
    private function stand(): array
    {
        return [
            'gruppen' => DB::table('permissions_groups')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all(),
            'rechte' => DB::table('permissions')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all(),
        ];
    }

    /** @return TestResponse<Response> */
    private function gruppeSpeichern(string $name, string $anzeige = 'Alpha'): TestResponse
    {
        return $this->actingAs($this->entwickler())->from(route('permission.edit', self::ALPHA))
            ->post(route('permission.update', self::ALPHA), ['group_display' => $anzeige, 'group_name' => $name]);
    }

    private function meldungNameVergeben(): string
    {
        return __('userauth::permission.NameVergeben');
    }

    // ---------------------------------------------------------------- AK1

    /** @return array<string, array{0: string}> */
    public static function crudArten(): array
    {
        return [
            'create' => ['create'],
            'read' => ['read'],
            'edit' => ['edit'],
            'delete' => ['delete'],
        ];
    }

    private function eigenesRechtLoeschen(string $art): void
    {
        $this->assertSame(1, DB::table('permissions')->where('id', $this->rechteIds['alpha_'.$art])->update(['deleted_at' => now()]));
    }

    // AK1
    #[DataProvider('crudArten')]
    public function test_ak1_gruppe_mit_eigenem_geloeschtem_recht_laesst_sich_mit_unveraendertem_namen_speichern(string $art): void
    {
        $this->eigenesRechtLoeschen($art);

        $antwort = $this->gruppeSpeichern('alpha', 'Alpha Neu');

        $antwort->assertSessionHasNoErrors();
        $antwort->assertRedirect(route('permission'));
        $this->assertSame('Alpha Neu', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_display'));
    }

    // AK1
    #[DataProvider('crudArten')]
    public function test_ak1_speichern_mit_eigenem_geloeschtem_recht_zeigt_erfolgsmeldung(string $art): void
    {
        $this->eigenesRechtLoeschen($art);

        $antwort = $this->gruppeSpeichern('alpha', 'Alpha Neu');

        $antwort->assertSessionHas('message', __('userauth::permission.MsgEditSuccess'));
        $antwort->assertSessionHas('alert-type', 'success');
    }

    // AK1
    public function test_ak1_die_uebrigen_rechte_bleiben_aktiv_und_das_geloeschte_bleibt_geloescht(): void
    {
        // Gegenprobe: der Aufbau löscht genau ein Recht
        $this->eigenesRechtLoeschen('read');
        $this->assertNotNull(DB::table('permissions')->where('permission', 'alpha_read')->value('deleted_at'));

        $this->gruppeSpeichern('alpha', 'Alpha Neu')->assertSessionHasNoErrors();

        $this->assertSame(
            ['alpha_create', 'alpha_edit', 'alpha_delete'],
            DB::table('permissions')->where('group_id', self::ALPHA)->whereNull('deleted_at')->orderBy('id')->pluck('permission')->all()
        );
        $this->assertNotNull(DB::table('permissions')->where('permission', 'alpha_read')->value('deleted_at'));
    }

    // AK1
    public function test_ak1_umbenennen_mit_eigenem_geloeschtem_recht_ohne_kollision_funktioniert(): void
    {
        $this->eigenesRechtLoeschen('read');

        $this->gruppeSpeichern('Gamma', 'Gamma Anzeige')->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $this->assertSame('gamma', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
        $this->assertSame(
            ['gamma_create', 'gamma_edit', 'gamma_delete'],
            DB::table('permissions')->where('group_id', self::ALPHA)->whereNull('deleted_at')->orderBy('id')->pluck('permission')->all()
        );
    }

    // ---------------------------------------------------------------- AK2

    /** @return array<string, array{0: int}> */
    public static function fremdeGruppen(): array
    {
        return [
            'gehört zu einer anderen Gruppe' => [self::BETA],
            'gehört zu keiner Gruppe' => [999],
        ];
    }

    // AK2
    #[DataProvider('fremdeGruppen')]
    public function test_ak2_fremdes_geloeschtes_recht_mit_neuem_namen_ergibt_fehler_am_feld_group_name(int $gruppe): void
    {
        $this->eigenesRechtLoeschen('read');
        $this->recht('gamma_read', 'Gamma lesen', $gruppe, 'read', now()->toDateTimeString());
        $vorher = $this->stand();

        $antwort = $this->gruppeSpeichern('Gamma', 'Gamma Anzeige');

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::ALPHA));
        $antwort->assertSessionHasErrors(['group_name' => $this->meldungNameVergeben()]);
        $this->assertSame($vorher, $this->stand());
    }

    // AK2
    #[DataProvider('fremdeGruppen')]
    public function test_ak2_gegenprobe_ohne_fremdes_recht_wird_umbenannt(int $gruppe): void
    {
        $this->eigenesRechtLoeschen('read');
        $this->recht('gamma_read', 'Gamma lesen', $gruppe, 'read', now()->toDateTimeString());

        // anderer Name ohne Kollision: der Aufbau greift
        $this->gruppeSpeichern('Delta', 'Delta Anzeige')->assertSessionHasNoErrors()->assertRedirect(route('permission'));
        $this->assertSame('delta', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
    }

    // ---------------------------------------------------------------- Randfall 1 (eigenes gelöschtes Recht trägt den Zielnamen)

    // Randfall 1
    public function test_randfall1_eigenes_geloeschtes_recht_mit_zielnamen_ergibt_fehler_am_feld_ohne_500(): void
    {
        $this->recht('gamma_read', 'Gamma lesen', self::ALPHA, 'read', now()->toDateTimeString());
        $vorher = $this->stand();

        $antwort = $this->gruppeSpeichern('Gamma', 'Gamma Anzeige');

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::ALPHA));
        $antwort->assertSessionHasErrors(['group_name' => $this->meldungNameVergeben()]);
        $this->assertSame($vorher, $this->stand());
    }

    // Randfall 1
    public function test_randfall1_gegenprobe_ohne_das_geloeschte_recht_wird_umbenannt(): void
    {
        $this->gruppeSpeichern('Delta', 'Delta Anzeige')->assertSessionHasNoErrors()->assertRedirect(route('permission'));
        $this->assertSame('delta', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
    }

    // ---------------------------------------------------------------- AK3

    // AK3
    public function test_ak3_no_permission_zeigt_den_text_mit_status_200(): void
    {
        $antwort = $this->actingAs($this->nutzer())->get('/no-permission');

        $antwort->assertOk();
        $antwort->assertSee(__('userauth::permission.no-permission'), false);
    }

    // AK3
    public function test_ak3_no_permission_schreibt_keinen_eintrag_ins_log(): void
    {
        Log::spy();

        // Gegenprobe: die Seite wurde ausgeliefert
        $this->actingAs($this->nutzer())->get('/no-permission')->assertOk();

        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('critical');
        Log::shouldNotHaveReceived('warning');
    }

    // ---------------------------------------------------------------- AK4

    // AK4
    public function test_ak4_ablehnung_endet_auf_der_seite_keine_berechtigung_mit_status_200(): void
    {
        config(['userauth.redirect_on_no_permission' => 'no-permission']);
        $nutzer = $this->nutzer();

        // Gegenprobe: ohne Folgen ist es die Weiterleitung auf die Seite, der Inhalt der Route bleibt verborgen
        $this->actingAs($nutzer)->get('/_t/zugang')->assertRedirect(route('no-permission'));

        $antwort = $this->actingAs($nutzer)->followingRedirects()->get('/_t/zugang');
        $antwort->assertOk();
        $antwort->assertSee(__('userauth::permission.no-permission'), false);
        $antwort->assertDontSee(self::GEHEIM);
    }

    // AK4
    public function test_ak4_gegenprobe_nutzer_mit_recht_erreicht_die_route(): void
    {
        config(['userauth.redirect_on_no_permission' => 'no-permission']);

        $this->actingAs($this->nutzer(self::ROLLE_ZUGANG))->followingRedirects()->get('/_t/zugang')
            ->assertOk()->assertSee(self::GEHEIM)->assertDontSee(__('userauth::permission.no-permission'), false);
    }

    // ---------------------------------------------------------------- Randfall 2 (andere HTTP-Methoden)

    /** @return array<string, array{0: string}> */
    public static function methoden(): array
    {
        return [
            'POST' => ['post'],
            'PUT' => ['put'],
            'PATCH' => ['patch'],
            'DELETE' => ['delete'],
        ];
    }

    // Randfall 2
    #[DataProvider('methoden')]
    public function test_randfall2_no_permission_mit_anderer_methode_verhaelt_sich_wie_get(string $methode): void
    {
        $nutzer = $this->nutzer();

        // Gegenprobe: GET liefert die Seite
        $this->actingAs($nutzer)->get('/no-permission')->assertOk();

        $antwort = $this->actingAs($nutzer)->{$methode}('/no-permission');
        $antwort->assertOk();
        $antwort->assertSee(__('userauth::permission.no-permission'), false);
    }

    // ---------------------------------------------------------------- AK5

    /** Deutsche Meldungstexte, damit die Beschriftung im Satz erkennbar ist (die Testbench-App hat keine deutschen Laravel-Texte). */
    private function deutscheMeldungen(): void
    {
        $this->app->setLocale('de');
        $this->app['translator']->addLines([
            'validation.required' => ':attribute fehlt',
            'validation.array' => ':attribute ist keine Liste',
            'validation.integer' => ':attribute ist keine Zahl',
            'validation.in' => ':attribute ist ungültig',
        ], 'de');
    }

    /**
     * @param  array<string, mixed>  $abweichung
     * @return array<string, mixed>
     */
    private function rollenDaten(array $abweichung = []): array
    {
        return array_merge(['role_display' => 'Editor', 'role' => 'editor', 'permission' => [$this->rechteIds['alpha_read']]], $abweichung);
    }

    /**
     * @param  array<string, mixed>  $daten
     * @return TestResponse<Response>
     */
    private function rolleSenden(bool $anlegen, array $daten): TestResponse
    {
        $nutzer = $this->nutzer();
        if ($anlegen) {
            return $this->actingAs($nutzer)->from(route('role.create'))->post(route('role.store'), $daten);
        }

        return $this->actingAs($nutzer)->from(route('role.edit', self::ROLLE_ZUGANG))->post(route('role.update', self::ROLLE_ZUGANG), $daten);
    }

    /** Beweist, dass der Aufbau greift: gültige Daten werden gespeichert. */
    private function gegenprobeRolleGreift(bool $anlegen): void
    {
        $daten = $anlegen ? $this->rollenDaten(['role_display' => 'Neu A', 'role' => 'neu a']) : $this->rollenDaten();
        $this->rolleSenden($anlegen, $daten)->assertSessionHasNoErrors()->assertRedirect(route('role'));
    }

    /** @return array<string, array{0: bool}> */
    public static function anlegenUndBearbeiten(): array
    {
        return [
            'Anlegen' => [true],
            'Bearbeiten' => [false],
        ];
    }

    // AK5
    #[DataProvider('anlegenUndBearbeiten')]
    public function test_ak5_role_display_nennt_die_beschriftung_des_formulars(bool $anlegen): void
    {
        $this->deutscheMeldungen();
        $this->gegenprobeRolleGreift($anlegen);

        $this->rolleSenden($anlegen, $this->rollenDaten(['role_display' => '']))->assertSessionHasErrors('role_display');

        $this->assertSame(__('userauth::role.role').' fehlt', session('errors')->first('role_display'));
    }

    // AK5
    #[DataProvider('anlegenUndBearbeiten')]
    public function test_ak5_role_nennt_die_beschriftung_des_formulars(bool $anlegen): void
    {
        $this->deutscheMeldungen();
        $this->gegenprobeRolleGreift($anlegen);

        $this->rolleSenden($anlegen, $this->rollenDaten(['role' => '']))->assertSessionHasErrors('role');

        $this->assertSame(__('userauth::role.role_intern').' fehlt', session('errors')->first('role'));
    }

    // AK5
    #[DataProvider('anlegenUndBearbeiten')]
    public function test_ak5_permission_nennt_die_beschriftung_der_rechte_liste(bool $anlegen): void
    {
        $this->deutscheMeldungen();
        $this->gegenprobeRolleGreift($anlegen);

        $this->rolleSenden($anlegen, $this->rollenDaten(['permission' => 'abc']))->assertSessionHasErrors('permission');

        $meldung = session('errors')->first('permission');
        $this->assertSame(__('userauth::permission.permission').' ist keine Liste', $meldung);
        $this->assertStringNotContainsString('permission', $meldung);
    }

    // AK5
    #[DataProvider('anlegenUndBearbeiten')]
    public function test_ak5_permission_eintrag_nennt_die_beschriftung_der_rechte_liste(bool $anlegen): void
    {
        $this->deutscheMeldungen();
        $this->gegenprobeRolleGreift($anlegen);

        $this->rolleSenden($anlegen, $this->rollenDaten(['permission' => [999999]]))->assertSessionHasErrors('permission.0');

        $meldung = session('errors')->first('permission.0');
        $this->assertSame(__('userauth::permission.permission').' ist ungültig', $meldung);
        $this->assertStringNotContainsString('permission', $meldung);
    }

    // AK5
    #[DataProvider('anlegenUndBearbeiten')]
    public function test_ak5_permission_eintrag_keine_zahl_nennt_die_beschriftung_der_rechte_liste(bool $anlegen): void
    {
        $this->deutscheMeldungen();
        $this->gegenprobeRolleGreift($anlegen);

        $this->rolleSenden($anlegen, $this->rollenDaten(['permission' => ['abc']]))->assertSessionHasErrors('permission.0');

        $meldungen = session('errors')->get('permission.0');
        $this->assertContains(__('userauth::permission.permission').' ist keine Zahl', $meldungen);
        foreach ($meldungen as $meldung) {
            $this->assertStringNotContainsString('permission', $meldung);
        }
    }
}
