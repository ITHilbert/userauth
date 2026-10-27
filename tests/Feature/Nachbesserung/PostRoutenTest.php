<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Nachbesserung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Http\Middleware\EnforcePasswordPolicy;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Logout und Impersonate nur per POST mit CSRF-Token.
 * Abgedeckt: AK3, AK4, Randfall 1, Randfall 2 (userauth-nachbesserung).
 *
 * Laravel überspringt die CSRF-Prüfung, wenn die Umgebung `testing` ist. Damit sie wirkt, wird die Umgebung der
 * Testanwendung auf `local` gesetzt; die Gegenprobe "mit gültigem Token klappt es" steht in jedem Test.
 *
 * Die Anmeldung steckt in der Sitzung (nicht in actingAs), damit "bleibt angemeldet" echt geprüft wird.
 */
final class PostRoutenTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_ADMIN = 2;

    private const ROLLE_NORMAL = 3;

    private const TOKEN = 'gueltiges-csrf-token-1234567890abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        // CSRF-Prüfung wirksam machen (PreventRequestForgery::runningUnitTests() prüft die Umgebung `testing`)
        $this->app['env'] = 'local';

        View::addLocation(__DIR__.'/../../Fixtures/views');
        config()->set('userauth.audit_log_enabled', false);
        config()->set('userauth.two_factor_enabled', false);
        config()->set('userauth.password_policy.enabled', false);
        config()->set('userauth.impersonate_enabled', true);

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => self::ROLLE_ADMIN, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => self::ROLLE_NORMAL, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
        DB::table('permissions')->insert([
            'id' => 1, 'permission' => 'impersonate_users', 'permission_display' => 'Nutzer übernehmen',
            'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);
        // Rolle "user" darf Nutzer übernehmen; Zielnutzer haben dieselbe (nicht privilegierte) Rolle
        DB::table('role_permission')->insert(['role_id' => self::ROLLE_NORMAL, 'permission_id' => 1]);
    }

    // ---------------------------------------------------------------- Hilfen

    private function sitzungsSchluessel(): string
    {
        return $this->app['auth']->guard()->getName();
    }

    /**
     * Meldet den Nutzer in der Sitzung an (ohne actingAs) und legt das CSRF-Token fest.
     *
     * @param  array<string, mixed>  $zusatz
     */
    private function anmelden(User $user, array $zusatz = []): void
    {
        $this->withSession(array_merge([$this->sitzungsSchluessel() => $user->id, '_token' => self::TOKEN], $zusatz));
    }

    /**
     * @param  array<string, mixed>  $daten
     */
    private function postMitToken(string $uri, array $daten = []): TestResponse
    {
        return $this->post($uri, $daten + ['_token' => self::TOKEN]);
    }

    private function angemeldeterNutzerId(): mixed
    {
        return session()->get($this->sitzungsSchluessel());
    }

    private function nutzer(string $email, int $rolle = self::ROLLE_NORMAL): User
    {
        return User::factory()->create(['email' => $email, 'role_id' => $rolle]);
    }

    // ---------------------------------------------------------------- AK3 Logout

    // AK3
    public function test_ak3_logout_per_get_ergibt_405_und_nutzer_bleibt_angemeldet(): void
    {
        $user = $this->nutzer('a@example.test');
        $this->anmelden($user);

        // Gegenprobe: die Sitzung trägt die Anmeldung wirklich
        $this->get(route('password.edit'))->assertOk();
        $this->assertSame($user->id, $this->angemeldeterNutzerId());

        $this->get(route('logout'))->assertStatus(405);

        $this->assertSame($user->id, $this->angemeldeterNutzerId());
    }

    // AK3
    public function test_ak3_logout_per_post_mit_gueltigem_token_meldet_ab(): void
    {
        $user = $this->nutzer('a@example.test');
        $this->anmelden($user);
        $this->get(route('password.edit'))->assertOk();
        $this->assertSame($user->id, $this->angemeldeterNutzerId(), 'Vorbedingung: angemeldet');

        $this->postMitToken(route('logout'))->assertRedirect();

        $this->assertNull($this->angemeldeterNutzerId());
    }

    // AK3
    public function test_ak3_logout_per_post_ohne_token_ergibt_419_und_nutzer_bleibt_angemeldet(): void
    {
        $user = $this->nutzer('a@example.test');
        $this->anmelden($user);

        // Gegenprobe: mit Token im selben Aufbau wird abgemeldet, ohne Token nicht
        $this->get(route('password.edit'))->assertOk();
        $this->assertSame($user->id, $this->angemeldeterNutzerId());

        $this->post(route('logout'))->assertStatus(419);

        $this->assertSame($user->id, $this->angemeldeterNutzerId());
    }

    // AK3
    public function test_ak3_logout_per_post_mit_falschem_token_ergibt_419_und_nutzer_bleibt_angemeldet(): void
    {
        $user = $this->nutzer('a@example.test');
        $this->anmelden($user);
        $this->get(route('password.edit'))->assertOk();

        $this->post(route('logout'), ['_token' => 'falsch'])->assertStatus(419);

        $this->assertSame($user->id, $this->angemeldeterNutzerId());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function andereMethodenFuerLogout(): array
    {
        return [
            'PUT' => ['put'],
            'PATCH' => ['patch'],
            'DELETE' => ['delete'],
        ];
    }

    // AK3 (nur POST, nicht nur "nicht GET")
    #[DataProvider('andereMethodenFuerLogout')]
    public function test_ak3_logout_nimmt_keine_anderen_methoden_an(string $methode): void
    {
        $user = $this->nutzer('a@example.test');
        $this->anmelden($user);
        $this->get(route('password.edit'))->assertOk();

        $this->{$methode}(route('logout'), ['_token' => self::TOKEN])->assertStatus(405);

        $this->assertSame($user->id, $this->angemeldeterNutzerId());
    }

    // ---------------------------------------------------------------- Randfall 1

    // Randfall 1
    public function test_randfall1_gast_mit_offener_zwei_faktor_anmeldung_bricht_per_post_logout_mit_token_ab(): void
    {
        $user = $this->nutzer('a@example.test');
        $this->withSession([
            '_token' => self::TOKEN,
            'userauth.2fa' => ['kennung' => 'kennung-1', 'nutzer_id' => $user->id, 'angemeldet_bleiben' => false, 'ziel' => null, 'code_hash' => null, 'code_gueltig_bis' => null],
        ]);

        // Gegenprobe: die Anmeldung ist offen (Code-Seite erreichbar), der Gast ist nicht angemeldet
        $this->get(route('verify.index'))->assertOk();
        $this->assertNull($this->angemeldeterNutzerId());
        $this->assertTrue(session()->has('userauth.2fa'));

        $antwort = $this->postMitToken(route('logout'));

        $this->assertContains($antwort->getStatusCode(), [301, 302, 303, 307, 308]);
        $this->assertFalse(session()->has('userauth.2fa'), 'Die offene Anmeldung ist abgebrochen');
        $this->assertNull($this->angemeldeterNutzerId());
    }

    // Randfall 1
    public function test_randfall1_formular_abbrechen_in_zwei_faktor_ansicht_ist_post_mit_csrf(): void
    {
        $this->assertMatchesRegularExpression(
            "/<form method=\"POST\" action=\"\\{\\{ route\\('logout'\\) \\}\\}\"[^>]*>\\s*@csrf/",
            (string) file_get_contents(__DIR__.'/../../../src/Resources/views/auth/twoFactor.blade.php')
        );
    }

    // ---------------------------------------------------------------- Randfall 2

    // Randfall 2
    public function test_randfall2_logout_behaelt_den_routennamen_und_die_passwort_richtlinie_laesst_ihn_durch(): void
    {
        $this->assertSame('logout', app('router')->getRoutes()->getByName('logout')?->getName());

        config()->set('userauth.password_policy.enabled', true);
        $user = $this->nutzer('a@example.test');
        // Abgelaufenes Passwort: die Richtlinie leitet alles außer den erlaubten Routen um
        DB::table('users')->where('id', $user->id)->update(['password_changed_at' => now()->subYears(5)]);
        $this->anmelden($user);

        $this->postMitToken(route('logout'))->assertRedirect();

        $this->assertNull($this->angemeldeterNutzerId(), 'logout wird von EnforcePasswordPolicy nicht umgeleitet');
    }

    // Randfall 2
    public function test_randfall2_enforce_password_policy_listet_logout_weiterhin_als_ausnahme(): void
    {
        $quelle = (string) file_get_contents((new \ReflectionClass(EnforcePasswordPolicy::class))->getFileName());

        $this->assertStringContainsString("'logout'", $quelle);
    }

    // ---------------------------------------------------------------- AK4 Impersonate

    // AK4
    public function test_ak4_impersonate_per_get_ergibt_405_und_nutzer_bleibt_unveraendert(): void
    {
        $admin = $this->nutzer('admin@example.test');
        $ziel = $this->nutzer('ziel@example.test');
        $this->anmelden($admin);
        $this->get(route('password.edit'))->assertOk();
        $this->assertSame($admin->id, $this->angemeldeterNutzerId());

        $this->get(route('userauth.impersonate', $ziel->id))->assertStatus(405);

        $this->assertSame($admin->id, $this->angemeldeterNutzerId());
        $this->assertFalse(session()->has('impersonated_by'));
    }

    // AK4
    public function test_ak4_impersonate_leave_per_get_ergibt_405_und_uebernahme_bleibt_bestehen(): void
    {
        $admin = $this->nutzer('admin@example.test');
        $ziel = $this->nutzer('ziel@example.test');
        $this->anmelden($ziel, ['impersonated_by' => $admin->id]);
        $this->get(route('password.edit'))->assertOk();
        $this->assertSame($admin->id, session()->get('impersonated_by'));

        $this->get(route('userauth.impersonate.leave'))->assertStatus(405);

        $this->assertSame($ziel->id, $this->angemeldeterNutzerId());
        $this->assertSame($admin->id, session()->get('impersonated_by'));
    }

    // AK4
    public function test_ak4_impersonate_per_post_mit_token_beginnt_die_uebernahme(): void
    {
        $admin = $this->nutzer('admin@example.test');
        $ziel = $this->nutzer('ziel@example.test');
        $this->anmelden($admin);

        $this->postMitToken(route('userauth.impersonate', $ziel->id))->assertRedirect();

        $this->assertSame($ziel->id, $this->angemeldeterNutzerId());
        $this->assertSame($admin->id, session()->get('impersonated_by'));
    }

    // AK4
    public function test_ak4_impersonate_leave_per_post_mit_token_beendet_die_uebernahme(): void
    {
        $admin = $this->nutzer('admin@example.test');
        $ziel = $this->nutzer('ziel@example.test');
        $this->anmelden($ziel, ['impersonated_by' => $admin->id]);

        $this->postMitToken(route('userauth.impersonate.leave'))->assertRedirect();

        $this->assertSame($admin->id, $this->angemeldeterNutzerId());
        $this->assertFalse(session()->has('impersonated_by'));
    }

    // AK4
    public function test_ak4_impersonate_per_post_ohne_token_ergibt_419_und_nichts_aendert_sich(): void
    {
        $admin = $this->nutzer('admin@example.test');
        $ziel = $this->nutzer('ziel@example.test');
        $this->anmelden($admin);
        $this->get(route('password.edit'))->assertOk();
        $this->assertSame($admin->id, $this->angemeldeterNutzerId());

        $this->post(route('userauth.impersonate', $ziel->id))->assertStatus(419);

        $this->assertSame($admin->id, $this->angemeldeterNutzerId());
        $this->assertFalse(session()->has('impersonated_by'));
    }

    // AK4
    public function test_ak4_impersonate_leave_per_post_ohne_token_ergibt_419_und_uebernahme_bleibt_bestehen(): void
    {
        $admin = $this->nutzer('admin@example.test');
        $ziel = $this->nutzer('ziel@example.test');
        $this->anmelden($ziel, ['impersonated_by' => $admin->id]);
        $this->get(route('password.edit'))->assertOk();
        $this->assertSame($admin->id, session()->get('impersonated_by'));

        $this->post(route('userauth.impersonate.leave'))->assertStatus(419);

        $this->assertSame($ziel->id, $this->angemeldeterNutzerId());
        $this->assertSame($admin->id, session()->get('impersonated_by'));
    }

    // AK4 (Gegenprobe zu "wie bisher": die Rechteprüfung gilt weiterhin auch per POST)
    public function test_ak4_impersonate_per_post_mit_token_ohne_recht_bleibt_verboten(): void
    {
        DB::table('role_permission')->where('role_id', self::ROLLE_NORMAL)->delete();
        $ohneRecht = $this->nutzer('ohne@example.test');
        $ziel = $this->nutzer('ziel@example.test');
        $this->anmelden($ohneRecht);
        $this->get(route('password.edit'))->assertOk();

        $this->postMitToken(route('userauth.impersonate', $ziel->id))->assertForbidden();

        $this->assertSame($ohneRecht->id, $this->angemeldeterNutzerId());
        $this->assertFalse(session()->has('impersonated_by'));
    }
}
