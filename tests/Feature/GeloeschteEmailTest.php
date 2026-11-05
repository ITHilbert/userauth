<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Tests\Fakes\FakeSocialIdentityProvider;
use ITHilbert\UserAuth\Tests\Feature\SocialLogin\SocialLoginTestCase;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * E-Mail eines gelöschten Benutzers: sperren | reaktivieren | neu (userauth-geloeschte-email).
 * Abgedeckt: AK1–AK8 und Randfälle 1–4 (AK9 ist Doku, nicht prüfbar).
 *
 * Aufbau: Social-Login-Testaufbau (Rollen 1–3, Fake-Anbieter); Admin hat Rolle 3 mit user_create.
 */
final class GeloeschteEmailTest extends SocialLoginTestCase
{
    private const EMAIL = 'alt@example.test';

    private const ALTES_PASSWORT = 'Altes-Passwort-1!';

    private const NEUES_PASSWORT = 'Neues-Passwort-2026!';

    private const ROLLE_FORMULAR = 3;

    private const ROLLE_ALT = 2;

    private const HINWEIS_DE = 'Gab es zu Ihrer E-Mail-Adresse ein gelöschtes Konto, wird es bei der Registrierung endgültig entfernt. Wenn Sie das nicht möchten, wenden Sie sich bitte an den Support.';

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        config()->set('userauth.password_policy.enabled', false);
        config()->set('userauth.geloeschte_email', 'sperren');

        $jetzt = now();
        foreach (['user_create'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            DB::table('role_permission')->insert(['role_id' => self::ROLLE_FORMULAR, 'permission_id' => $i + 1]);
        }

        // Admin vor den Tests anlegen: Zählungen und Model-Ereignisse der Tests bleiben davon unberührt.
        $this->admin();
    }

    protected function tearDown(): void
    {
        User::flushEventListeners();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- Hilfen

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::factory()->create(['role_id' => self::ROLLE_FORMULAR, 'email' => 'admin@example.test']);
    }

    /**
     * Weich gelöschter Benutzer mit Verknüpfungen aller Art.
     *
     * @param  array<string, mixed>  $attribute
     */
    private function geloescht(array $attribute = [], bool $verknuepft = true): User
    {
        $nutzer = User::factory()->create(array_merge([
            'email' => self::EMAIL,
            'name' => 'Alter Name',
            'password' => Hash::make(self::ALTES_PASSWORT),
            'role_id' => self::ROLLE_ALT,
            'city' => 'Altstadt',
            'image' => 'storage/users/alt.jpg',
            'email_verified_at' => null,
        ], $attribute));

        if ($verknuepft) {
            $this->verknuepfungenAnlegen($nutzer);
        }
        $nutzer->delete();

        return $nutzer;
    }

    private function verknuepfungenAnlegen(User $nutzer): int
    {
        $jetzt = now();
        $teamId = DB::table('teams')->insertGetId(['name' => 'Team-'.$nutzer->id, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        DB::table('team_user')->insert(['team_id' => $teamId, 'user_id' => $nutzer->id, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        DB::table('users')->where('id', $nutzer->id)->update([
            'current_team_id' => $teamId,
            'remember_token' => 'altes-remember-token',
            'two_factor_code' => 'alter-code-hash',
            'two_factor_expires_at' => $jetzt->copy()->addMinutes(10),
        ]);
        DB::table('user_social_accounts')->insert([
            'user_id' => $nutzer->id, 'provider' => 'google', 'provider_user_id' => 'google-alt-'.$nutzer->id,
            'email' => $nutzer->email, 'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);
        DB::table('user_pending_social_links')->insert([
            'user_id' => $nutzer->id, 'provider' => 'google', 'provider_user_id' => 'google-pending-'.$nutzer->id,
            'token_hash' => str_pad((string) $nutzer->id, 64, 'a'), 'expires_at' => $jetzt->copy()->addHour(),
            'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);
        DB::table('password_reset_tokens')->upsert([['email' => strtolower($nutzer->email), 'token' => 'hash', 'created_at' => $jetzt]], ['email'], ['token']);
        DB::table('user_password_histories')->insert([
            'user_id' => $nutzer->id, 'password' => Hash::make(self::ALTES_PASSWORT), 'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);
        DB::table('user_auth_audit_logs')->insert([
            'user_id' => $nutzer->id, 'email' => $nutzer->email, 'event' => 'login', 'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);

        return $teamId;
    }

    /**
     * Ein fremder, aktiver Benutzer mit eigenen Verknüpfungen (muss immer unberührt bleiben).
     *
     * @return array<string, int>
     */
    private function fremderNutzer(): array
    {
        $fremd = User::factory()->create(['email' => 'fremd@example.test', 'role_id' => self::ROLLE_FORMULAR]);
        $this->verknuepfungenAnlegen($fremd);

        return $this->zaehler($fremd->id, 'fremd@example.test');
    }

    /**
     * @return array<string, int>
     */
    private function zaehler(int $userId, string $email): array
    {
        return [
            'social' => DB::table('user_social_accounts')->where('user_id', $userId)->count(),
            'pending' => DB::table('user_pending_social_links')->where('user_id', $userId)->count(),
            'team_user' => DB::table('team_user')->where('user_id', $userId)->count(),
            'historie' => DB::table('user_password_histories')->where('user_id', $userId)->count(),
            'tokens' => DB::table('password_reset_tokens')->where('email', strtolower($email))->count(),
            'audit' => DB::table('user_auth_audit_logs')->where('user_id', $userId)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function daten(array $extra = []): array
    {
        return array_merge([
            'name' => 'Neuer Name',
            'email' => self::EMAIL,
            'password' => self::NEUES_PASSWORT,
            'password2' => self::NEUES_PASSWORT,
            'role_id' => self::ROLLE_FORMULAR,
            'city' => 'Neustadt',
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $daten
     */
    private function anlegen(array $daten, ?User $admin = null): TestResponse
    {
        return $this->actingAs($admin ?? $this->admin())->post(route('user.store'), $daten);
    }

    private function option(string $wert): void
    {
        config()->set('userauth.geloeschte_email', $wert);
    }

    /**
     * @return array<string, mixed>
     */
    private function rohzeile(int $id): array
    {
        return (array) DB::table('users')->where('id', $id)->first();
    }

    private function benutzerAnzahl(string $email = self::EMAIL): int
    {
        return DB::table('users')->whereRaw('lower(email) = ?', [strtolower($email)])->count();
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function ungueltigeOptionen(): array
    {
        return [
            'nicht gesetzt (null)' => [null],
            'leer gesetzt' => [''],
            'unbekannter Wert' => ['loeschen'],
            'Großschreibung Neu' => ['Neu'],
            'Großschreibung REAKTIVIEREN' => ['REAKTIVIEREN'],
            'Zahl' => [1],
            'Liste' => [['neu']],
            'Wahrheitswert' => [true],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function alleOptionen(): array
    {
        return ['sperren' => ['sperren'], 'reaktivieren' => ['reaktivieren'], 'neu' => ['neu']];
    }

    // ---------------------------------------------------------------- AK1

    // AK1
    public function test_ak1_paket_config_liefert_ohne_host_config_den_standard_sperren(): void
    {
        $this->assertArrayHasKey('geloeschte_email', config('userauth'));
        $this->assertSame('sperren', (require __DIR__.'/../../src/Config/config.php')['geloeschte_email']);
    }

    // AK1
    public function test_ak1_config_kommt_aus_der_umgebungsvariable_userauth_geloeschte_email(): void
    {
        $datei = __DIR__.'/../../src/Config/config.php';
        $this->assertSame('sperren', (require $datei)['geloeschte_email'], 'Gegenprobe: ohne Variable gilt der Standard');
        try {
            foreach (['reaktivieren', 'neu'] as $wert) {
                putenv('USERAUTH_GELOESCHTE_EMAIL='.$wert);
                $this->assertSame($wert, (require $datei)['geloeschte_email']);
            }
        } finally {
            putenv('USERAUTH_GELOESCHTE_EMAIL');
        }
    }

    // AK1
    #[DataProvider('ungueltigeOptionen')]
    public function test_ak1_jeder_andere_wert_wirkt_wie_sperren(mixed $wert): void
    {
        // Gegenprobe: Der Config-Schlüssel wirkt – mit reaktivieren wird eine andere gelöschte E-Mail angelegt.
        $this->option('reaktivieren');
        $andere = $this->geloescht(['email' => 'andere@example.test'], false);
        $this->anlegen($this->daten(['email' => 'andere@example.test']))->assertSessionHasNoErrors();
        $this->assertNull($this->rohzeile($andere->id)['deleted_at'], 'Gegenprobe: reaktivieren stellt wieder her');

        config()->set('userauth.geloeschte_email', $wert);
        $alt = $this->geloescht();
        $antwort = $this->anlegen($this->daten());

        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein Serverfehler');
        $antwort->assertSessionHasErrors('email');
        $this->assertNotNull($this->rohzeile($alt->id)['deleted_at'], 'Zeile bleibt gelöscht');
        $this->assertSame(1, $this->benutzerAnzahl());
    }

    // AK1
    #[DataProvider('ungueltigeOptionen')]
    public function test_ak1_jeder_andere_wert_wirkt_bei_social_registrierung_wie_sperren(mixed $wert): void
    {
        config()->set('userauth.social.allow_registration', true);
        config()->set('userauth.geloeschte_email', 'reaktivieren');
        $andere = $this->geloescht(['email' => 'andere@example.test'], false);
        $this->provider->returnIdentity('google-sub-andere', 'andere@example.test');
        $this->socialCallback()->assertRedirect('/');
        $this->assertAuthenticatedAs($andere->fresh());
        $this->app['auth']->guard()->logout();

        config()->set('userauth.geloeschte_email', $wert);
        $alt = $this->geloescht();
        $this->provider->returnIdentity('google-sub-1', self::EMAIL);

        $antwort = $this->socialCallback();

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors(['social' => __('userauth::social.errors.account_disabled')]);
        $this->assertGuest();
        $this->assertNotNull($this->rohzeile($alt->id)['deleted_at']);
    }

    // ---------------------------------------------------------------- AK2

    // AK2
    public function test_ak2_sperren_gibt_validierungsfehler_am_feld_email_mit_meldung_de(): void
    {
        app()->setLocale('de');
        $this->option('sperren');
        $alt = $this->geloescht();
        $vorher = DB::table('users')->count();

        // Gegenprobe: Aufbau greift – eine freie E-Mail wird angelegt.
        $this->anlegen($this->daten(['email' => 'frei@example.test']))->assertSessionHasNoErrors();
        $this->assertSame($vorher + 1, DB::table('users')->count());

        $vorher = DB::table('users')->count();
        $antwort = $this->anlegen($this->daten());

        $antwort->assertSessionHasErrors('email');
        $this->assertSame(['Diese E-Mail-Adresse gehört zu einem gelöschten Benutzer.'], session('errors')->get('email'));
        $this->assertSame($vorher, DB::table('users')->count(), 'keine neue Zeile');
        $this->assertNotNull($this->rohzeile($alt->id)['deleted_at'], 'gelöschte Zeile bleibt gelöscht');
    }

    // AK2
    public function test_ak2_meldung_en(): void
    {
        app()->setLocale('en');
        $this->option('sperren');
        $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHasErrors('email');

        $this->assertSame(['This e-mail address belongs to a deleted user.'], session('errors')->get('email'));
    }

    // AK2
    public function test_ak2_groesskleinschreibung_der_email_ist_egal(): void
    {
        app()->setLocale('de');
        $this->option('sperren');
        $alt = $this->geloescht();
        $vorher = DB::table('users')->count();

        $this->anlegen($this->daten(['email' => 'ALT@Example.TEST']))->assertSessionHasErrors('email');

        $this->assertSame(['Diese E-Mail-Adresse gehört zu einem gelöschten Benutzer.'], session('errors')->get('email'));
        $this->assertSame($vorher, DB::table('users')->count());
        $this->assertNotNull($this->rohzeile($alt->id)['deleted_at']);
    }

    // AK2
    public function test_ak2_fehler_erscheint_in_der_gerenderten_seite_am_formular(): void
    {
        app()->setLocale('de');
        $this->option('sperren');
        $this->geloescht();
        $admin = $this->admin();
        $formular = route('user.create');

        $this->actingAs($admin)->get($formular)->assertOk();
        $this->actingAs($admin)->from($formular)->post(route('user.store'), $this->daten())->assertSessionHasErrors('email');
        $meldung = session('errors')->first('email');

        $antwort = $this->actingAs($admin)->from($formular)->followingRedirects()
            ->post(route('user.store'), $this->daten())
            ->assertSee(e($meldung), false);

        $this->assertSame(1, substr_count($antwort->getContent(), e($meldung)), 'Meldung genau einmal in der Seite');
    }

    // ---------------------------------------------------------------- AK3

    // AK3
    public function test_ak3_reaktivieren_stellt_dieselbe_zeile_wieder_her_und_uebernimmt_formularwerte(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloescht();
        $vorher = DB::table('users')->count();

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $zeile = $this->rohzeile($alt->id);
        $this->assertNull($zeile['deleted_at'], 'wiederhergestellt');
        $this->assertSame($vorher, DB::table('users')->count(), 'keine neue Zeile');
        $this->assertSame(1, $this->benutzerAnzahl());
        $this->assertSame('Neuer Name', $zeile['name']);
        $this->assertSame('Neustadt', $zeile['city'], 'Profilfeld aus dem Formular');
        $this->assertSame(self::ROLLE_FORMULAR, (int) $zeile['role_id'], 'Rolle aus dem Formular, nicht die alte');
    }

    // AK3
    public function test_ak3_passwort_kommt_aus_dem_formular(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $hash = $this->rohzeile($alt->id)['password'];
        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $hash), 'neues Passwort');
        $this->assertFalse(Hash::check(self::ALTES_PASSWORT, $hash), 'altes Passwort gilt nicht mehr');
    }

    // AK3
    public function test_ak3_bild_kommt_aus_dem_formular(): void
    {
        Storage::fake('public');
        $this->option('reaktivieren');
        $alt = $this->geloescht();

        $this->anlegen($this->daten(['image' => UploadedFile::fake()->image('neu.jpg')]))->assertSessionHasNoErrors();

        $bild = (string) $this->rohzeile($alt->id)['image'];
        $this->assertNotSame('storage/users/alt.jpg', $bild);
        $this->assertStringStartsWith('storage/', $bild, 'Gegenprobe: neues Bild gesetzt');
        $this->assertTrue(Storage::disk('public')->exists(substr($bild, strlen('storage/'))));
    }

    // AK3
    public function test_ak3_ohne_bild_im_formular_bleibt_das_alte_bild_nicht_erhalten(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $this->assertEmpty($this->rohzeile($alt->id)['image'], 'wie bei einer Neuanlage ohne Bild');
    }

    // AK3
    public function test_ak3_passwortregeln_gelten_und_nichts_wird_wiederhergestellt(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloescht();
        $vorher = $this->zaehler($alt->id, self::EMAIL);

        $antwort = $this->anlegen($this->daten(['password' => 'Abc1234', 'password2' => 'Abc1234']));

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('password');
        $this->assertNotNull($this->rohzeile($alt->id)['deleted_at'], 'Zeile bleibt gelöscht');
        $this->assertSame($vorher, $this->zaehler($alt->id, self::EMAIL));

        // Gegenprobe: mit gültigem Passwort geht es.
        $this->anlegen($this->daten())->assertSessionHasNoErrors();
        $this->assertNull($this->rohzeile($alt->id)['deleted_at']);
    }

    // AK3
    public function test_ak3_social_konten_und_offene_verknuepfungen_werden_geloescht_fremde_bleiben(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloescht();
        $fremd = $this->fremderNutzer();
        $this->assertSame(1, $this->zaehler($alt->id, self::EMAIL)['social'], 'Ausgangslage');
        $this->assertSame(1, $this->zaehler($alt->id, self::EMAIL)['pending'], 'Ausgangslage');

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $this->assertSame(0, DB::table('user_social_accounts')->where('user_id', $alt->id)->count());
        $this->assertSame(0, DB::table('user_pending_social_links')->where('user_id', $alt->id)->count());
        $fremdId = (int) DB::table('users')->where('email', 'fremd@example.test')->value('id');
        $this->assertSame($fremd, $this->zaehler($fremdId, 'fremd@example.test'), 'Daten anderer Benutzer unberührt');
    }

    // AK3
    public function test_ak3_teamzuordnungen_entfernt_aktives_team_leer_und_team_bleibt(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloescht();
        $fremd = $this->fremderNutzer();
        $teamsVorher = DB::table('teams')->count();
        $this->assertNotNull($this->rohzeile($alt->id)['current_team_id'], 'Ausgangslage');

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $this->assertSame(0, DB::table('team_user')->where('user_id', $alt->id)->count());
        $this->assertNull($this->rohzeile($alt->id)['current_team_id']);
        $this->assertSame($teamsVorher, DB::table('teams')->count(), 'Randfall 2: Teams selbst bleiben bestehen');
        $fremdId = (int) DB::table('users')->where('email', 'fremd@example.test')->value('id');
        $this->assertSame($fremd['team_user'], DB::table('team_user')->where('user_id', $fremdId)->count());
    }

    // AK3
    public function test_ak3_remember_token_wird_neu_erzeugt(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $token = $this->rohzeile($alt->id)['remember_token'];
        $this->assertNotSame('altes-remember-token', $token);
        $this->assertNotEmpty($token, 'neu erzeugt, nicht leer');
    }

    // AK3
    public function test_ak3_passwort_reset_tokens_zur_email_werden_geloescht_andere_bleiben(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloescht();
        DB::table('password_reset_tokens')->insert(['email' => 'andere@example.test', 'token' => 'x', 'created_at' => now()]);
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', self::EMAIL)->count(), 'Ausgangslage');

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', self::EMAIL)->count());
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', 'andere@example.test')->count());
    }

    // AK3
    public function test_ak3_zwei_faktor_code_und_ablauf_werden_geleert(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloescht();
        $this->assertNotNull($this->rohzeile($alt->id)['two_factor_code'], 'Ausgangslage');

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $this->assertNull($this->rohzeile($alt->id)['two_factor_code']);
        $this->assertNull($this->rohzeile($alt->id)['two_factor_expires_at']);
    }

    // AK3
    public function test_ak3_passwort_historie_und_audit_log_bleiben(): void
    {
        $this->option('reaktivieren');
        config()->set('userauth.audit_log_enabled', false);
        $alt = $this->geloescht();
        $vorher = $this->zaehler($alt->id, self::EMAIL);
        $this->assertSame(1, $vorher['historie']);
        $this->assertSame(1, $vorher['audit']);

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $nachher = $this->zaehler($alt->id, self::EMAIL);
        $this->assertSame($vorher['historie'], $nachher['historie']);
        $this->assertSame($vorher['audit'], $nachher['audit']);
    }

    // AK3
    public function test_ak3_erfolgsmeldung_de(): void
    {
        app()->setLocale('de');
        $this->option('reaktivieren');
        $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHas('message', 'Der gelöschte Benutzer wurde wiederhergestellt.');
    }

    // AK3
    public function test_ak3_erfolgsmeldung_en(): void
    {
        app()->setLocale('en');
        $this->option('reaktivieren');
        $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHas('message', 'The deleted user has been restored.');
    }

    // AK3
    public function test_ak3_scheitert_ein_schritt_bleibt_alles_wie_vorher(): void
    {
        $this->option('reaktivieren');
        config()->set('userauth.password_policy.enabled', true);
        config()->set('userauth.password_policy.prevent_reuse_last_passwords', 3);
        $alt = $this->geloescht();
        DB::table('users')->where('id', $alt->id)->whereNotNull('deleted_at')->update(['password_changed_at' => now()->subDays(10)]);
        $zeileVorher = $this->rohzeile($alt->id);
        $vorher = $this->zaehler($alt->id, self::EMAIL);

        // Das alte Passwort steht in der Historie und ist als aktuelles Passwort gesperrt: PasswortSetzen lehnt ab.
        $antwort = $this->anlegen($this->daten(['password' => self::ALTES_PASSWORT, 'password2' => self::ALTES_PASSWORT]));

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('password');
        $this->assertSame($zeileVorher, $this->rohzeile($alt->id), 'Zeile bleibt gelöscht und unverändert');
        $this->assertSame($vorher, $this->zaehler($alt->id, self::EMAIL), 'Verknüpfungen unverändert');
        $this->assertNotNull($zeileVorher['deleted_at'], 'Gegenprobe: Ausgangslage gelöscht');
        $this->assertNotNull($this->rohzeile($alt->id)['current_team_id']);
        $this->assertSame(1, DB::table('team_user')->where('user_id', $alt->id)->count());
    }

    // ---------------------------------------------------------------- AK4

    // AK4
    public function test_ak4_neu_loescht_die_alte_zeile_endgueltig_und_legt_neu_an(): void
    {
        $this->option('neu');
        $alt = $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertSame(1, $this->benutzerAnzahl());
        $neu = DB::table('users')->where('email', self::EMAIL)->first();
        $this->assertNotSame($alt->id, (int) $neu->id, 'neue id');
        $this->assertNull($neu->deleted_at);
        $this->assertSame('Neuer Name', $neu->name);
        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $neu->password));
        $this->assertSame(0, DB::table('users')->where('id', $alt->id)->count(), 'alte Zeile endgültig weg');
    }

    // AK4
    public function test_ak4_verknuepfungen_der_alten_zeile_werden_entfernt_team_und_fremde_bleiben(): void
    {
        $this->option('neu');
        config()->set('userauth.audit_log_enabled', false);
        $alt = $this->geloescht();
        $fremd = $this->fremderNutzer();
        $teamsVorher = DB::table('teams')->count();
        $this->assertSame(['social' => 1, 'pending' => 1, 'team_user' => 1, 'historie' => 1, 'tokens' => 1, 'audit' => 1], $this->zaehler($alt->id, self::EMAIL), 'Ausgangslage');

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('users')->where('id', $alt->id)->count(), 'Gegenprobe: alte Zeile weg');
        $nachher = $this->zaehler($alt->id, self::EMAIL);
        $this->assertSame(0, $nachher['social']);
        $this->assertSame(0, $nachher['pending']);
        $this->assertSame(0, $nachher['team_user']);
        $this->assertSame(0, $nachher['historie']);
        $this->assertSame(0, $nachher['tokens']);
        $this->assertSame($teamsVorher, DB::table('teams')->count(), 'Teams selbst bleiben');
        $fremdId = (int) DB::table('users')->where('email', 'fremd@example.test')->value('id');
        $this->assertSame($fremd, $this->zaehler($fremdId, 'fremd@example.test'), 'fremde Daten unberührt');
    }

    // AK4
    public function test_ak4_audit_logs_bleiben_unveraendert_stehen(): void
    {
        $this->option('neu');
        config()->set('userauth.audit_log_enabled', false);
        $alt = $this->geloescht();
        $vorher = DB::table('user_auth_audit_logs')->get()->map(fn ($z) => (array) $z)->all();
        $this->assertCount(1, $vorher);

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('users')->where('id', $alt->id)->count(), 'Gegenprobe: alte Zeile weg');
        $this->assertSame($vorher, DB::table('user_auth_audit_logs')->get()->map(fn ($z) => (array) $z)->all());
    }

    // AK4
    public function test_ak4_erfolgsmeldung_de(): void
    {
        app()->setLocale('de');
        $this->option('neu');
        $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHas('message', 'Ein gelöschtes Konto mit dieser E-Mail-Adresse wurde endgültig entfernt und der Benutzer neu angelegt.');
    }

    // AK4
    public function test_ak4_erfolgsmeldung_en(): void
    {
        app()->setLocale('en');
        $this->option('neu');
        $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHas('message');
        $en = session('message');
        $this->assertNotSame('', trim((string) $en));
        $this->assertStringNotContainsString('::', (string) $en, 'Übersetzung vorhanden, kein Schlüssel');
        $this->assertNotSame('Ein gelöschtes Konto mit dieser E-Mail-Adresse wurde endgültig entfernt und der Benutzer neu angelegt.', $en);
    }

    // AK4
    public function test_ak4_scheitert_die_neuanlage_bleibt_die_alte_zeile_samt_verknuepfungen(): void
    {
        $this->option('neu');
        $alt = $this->geloescht();
        $zeileVorher = $this->rohzeile($alt->id);
        $vorher = $this->zaehler($alt->id, self::EMAIL);
        $aufgerufen = 0;
        User::creating(function () use (&$aufgerufen): void {
            $aufgerufen++;
            throw new RuntimeException('Neuanlage scheitert');
        });

        $this->anlegen($this->daten());

        $this->assertGreaterThan(0, $aufgerufen, 'Gegenprobe: die Neuanlage wurde versucht und ist gescheitert');
        $this->assertSame($zeileVorher, $this->rohzeile($alt->id), 'alte Zeile unverändert gelöscht');
        $this->assertSame($vorher, $this->zaehler($alt->id, self::EMAIL), 'Verknüpfungen erhalten');
        $this->assertSame(1, $this->benutzerAnzahl());
    }

    // ---------------------------------------------------------------- AK5

    // AK5
    #[DataProvider('alleOptionen')]
    public function test_ak5_aktiver_benutzer_blockiert_in_jeder_option_mit_unique_fehler(string $option): void
    {
        app()->setLocale('en');
        $this->option($option);
        $aktiv = User::factory()->create(['email' => self::EMAIL, 'name' => 'Aktiver', 'role_id' => self::ROLLE_FORMULAR]);
        $vorher = $this->rohzeile($aktiv->id);
        $anzahl = DB::table('users')->count();

        // Gegenprobe: Aufbau greift – eine freie E-Mail wird angelegt.
        $this->anlegen($this->daten(['email' => 'frei@example.test']))->assertSessionHasNoErrors();
        $this->assertSame($anzahl + 1, DB::table('users')->count());

        $antwort = $this->anlegen($this->daten());

        $antwort->assertSessionHasErrors('email');
        $this->assertSame([__('validation.unique', ['attribute' => 'email'])], session('errors')->get('email'));
        $this->assertSame($anzahl + 1, DB::table('users')->count(), 'keine weitere Zeile');
        $this->assertSame($vorher, $this->rohzeile($aktiv->id), 'aktiver Benutzer unverändert');
    }

    // ---------------------------------------------------------------- AK6

    private function socialZiel(string $option, bool $registrierung = true): User
    {
        config()->set('userauth.social.allow_registration', $registrierung);
        $this->option($option);
        $alt = $this->geloescht();
        $this->provider->returnIdentity('google-sub-1', self::EMAIL);

        return $alt;
    }

    // AK6
    public function test_ak6_sperren_wirft_account_disabled_wie_bisher(): void
    {
        $alt = $this->socialZiel('sperren');
        $vorher = $this->zaehler($alt->id, self::EMAIL);
        $zeile = $this->rohzeile($alt->id);

        $this->socialCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['social' => __('userauth::social.errors.account_disabled')]);

        $this->assertGuest();
        $this->assertSame($zeile, $this->rohzeile($alt->id));
        $this->assertSame($vorher, $this->zaehler($alt->id, self::EMAIL));
        $this->assertSame(1, $this->benutzerAnzahl());
    }

    // AK6
    public function test_ak6_reaktivieren_stellt_wieder_her_rolle_und_bestaetigung_gesetzt_passwort_bleibt(): void
    {
        $alt = $this->socialZiel('reaktivieren');
        $hash = $this->rohzeile($alt->id)['password'];
        $this->assertNull($this->rohzeile($alt->id)['email_verified_at'], 'Ausgangslage unbestätigt');

        $this->socialCallback()->assertRedirect('/');

        $zeile = $this->rohzeile($alt->id);
        $this->assertNull($zeile['deleted_at']);
        $this->assertSame(1, $this->benutzerAnzahl());
        $this->assertSame(3, (int) $zeile['role_id'], 'Rolle aus social.default_role');
        $this->assertNotNull($zeile['email_verified_at'], 'E-Mail als bestätigt');
        $this->assertSame($hash, $zeile['password'], 'Passwort-Hash bleibt');
    }

    // AK6
    public function test_ak6_reaktivieren_verknuepft_social_account_und_meldet_an(): void
    {
        $alt = $this->socialZiel('reaktivieren');

        $this->socialCallback()->assertRedirect('/');

        $this->assertAuthenticatedAs(User::query()->findOrFail($alt->id));
        $konten = DB::table('user_social_accounts')->where('user_id', $alt->id)->get();
        $this->assertCount(1, $konten, 'nur das neue Konto, die alten sind bereinigt');
        $this->assertSame('google-sub-1', $konten[0]->provider_user_id);
    }

    // AK6
    public function test_ak6_reaktivieren_bereinigt_wie_ak3(): void
    {
        config()->set('userauth.audit_log_enabled', false);
        $alt = $this->socialZiel('reaktivieren');
        $fremd = $this->fremderNutzer();
        $teamsVorher = DB::table('teams')->count();
        $vorher = $this->zaehler($alt->id, self::EMAIL);

        $this->socialCallback()->assertRedirect('/');

        $zeile = $this->rohzeile($alt->id);
        $this->assertNull($zeile['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $this->assertSame(0, DB::table('user_pending_social_links')->where('user_id', $alt->id)->count());
        $this->assertSame(0, DB::table('team_user')->where('user_id', $alt->id)->count());
        $this->assertNull($zeile['current_team_id']);
        $this->assertNotSame('altes-remember-token', $zeile['remember_token']);
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', self::EMAIL)->count());
        $this->assertNull($zeile['two_factor_code']);
        $this->assertNull($zeile['two_factor_expires_at']);
        $nachher = $this->zaehler($alt->id, self::EMAIL);
        $this->assertSame($vorher['historie'], $nachher['historie'], 'Historie bleibt');
        $this->assertSame($vorher['audit'], $nachher['audit'], 'Audit-Logs bleiben');
        $this->assertSame($teamsVorher, DB::table('teams')->count());
        $fremdId = (int) DB::table('users')->where('email', 'fremd@example.test')->value('id');
        $this->assertSame($fremd, $this->zaehler($fremdId, 'fremd@example.test'));
    }

    // AK6
    public function test_ak6_neu_loescht_alte_zeile_endgueltig_und_registriert_normal(): void
    {
        config()->set('userauth.audit_log_enabled', false);
        $alt = $this->socialZiel('neu', true);
        $auditVorher = DB::table('user_auth_audit_logs')->count();

        $this->socialCallback()->assertRedirect('/');

        $this->assertSame(0, DB::table('users')->where('id', $alt->id)->count(), 'alte Zeile endgültig weg');
        $neu = User::query()->where('email', self::EMAIL)->firstOrFail();
        $this->assertNotSame($alt->id, $neu->id);
        $this->assertAuthenticatedAs($neu);
        $this->assertSame(3, (int) $neu->role_id);
        $this->assertNotNull($neu->email_verified_at);
        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $neu->id, 'provider_user_id' => 'google-sub-1']);
        $zaehler = $this->zaehler($alt->id, self::EMAIL);
        $this->assertSame(0, $zaehler['social']);
        $this->assertSame(0, $zaehler['pending']);
        $this->assertSame(0, $zaehler['team_user']);
        $this->assertSame(0, $zaehler['historie']);
        $this->assertSame(0, $zaehler['tokens']);
        $this->assertSame($auditVorher, DB::table('user_auth_audit_logs')->count(), 'Audit-Logs bleiben');
    }

    // AK6
    #[DataProvider('alleOptionen')]
    public function test_ak6_ohne_allow_registration_bleibt_in_jeder_option_alles_wie_heute(string $option): void
    {
        $alt = $this->socialZiel($option, false);
        $vorher = $this->zaehler($alt->id, self::EMAIL);
        $zeile = $this->rohzeile($alt->id);

        // Gegenprobe: Ausgangslage gelöscht, Anbieter-Identität liefert die gelöschte E-Mail.
        $this->socialCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['social' => __('userauth::social.errors.account_disabled')]);

        $this->assertGuest();
        $this->assertSame($zeile, $this->rohzeile($alt->id), 'Zeile bleibt gelöscht und unverändert');
        $this->assertSame($vorher, $this->zaehler($alt->id, self::EMAIL));
        $this->assertSame(1, $this->benutzerAnzahl());
        $this->assertDatabaseMissing('user_social_accounts', ['provider_user_id' => 'google-sub-1']);
    }

    // ---------------------------------------------------------------- AK7

    /**
     * @return array<string, array{0: string}>
     */
    public static function themes(): array
    {
        return ['tailwind' => ['tailwind'], 'bootstrap' => ['bootstrap']];
    }

    private function hinweisAn(): void
    {
        $this->option('neu');
        config()->set('userauth.social.allow_registration', true);
        app()->setLocale('de');
    }

    // AK7
    #[DataProvider('themes')]
    public function test_ak7_hinweis_erscheint_bei_neu_registrierung_an_und_anbieter_verfuegbar(string $theme): void
    {
        config()->set('userauth.theme', $theme);
        $this->hinweisAn();

        $this->get(route('login'))->assertOk()
            ->assertSee(route('userauth.social.redirect', 'google'), false)
            ->assertSee(self::HINWEIS_DE, false);
    }

    // AK7
    #[DataProvider('themes')]
    public function test_ak7_hinweis_erscheint_nicht_bei_sperren_reaktivieren_und_ungueltigem_wert(string $theme): void
    {
        config()->set('userauth.theme', $theme);
        $this->hinweisAn();
        $this->get(route('login'))->assertSee(self::HINWEIS_DE, false);

        foreach (['sperren', 'reaktivieren', '', null, 'foo'] as $wert) {
            config()->set('userauth.geloeschte_email', $wert);
            $this->get(route('login'))->assertOk()
                ->assertSee(route('userauth.social.redirect', 'google'), false)
                ->assertDontSee(self::HINWEIS_DE, false);
        }
    }

    // AK7
    #[DataProvider('themes')]
    public function test_ak7_hinweis_erscheint_nicht_ohne_social_registrierung(string $theme): void
    {
        config()->set('userauth.theme', $theme);
        $this->hinweisAn();
        $this->get(route('login'))->assertSee(self::HINWEIS_DE, false);

        config()->set('userauth.social.allow_registration', false);

        $this->get(route('login'))->assertOk()
            ->assertSee(route('userauth.social.redirect', 'google'), false)
            ->assertDontSee(self::HINWEIS_DE, false);
    }

    // AK7
    #[DataProvider('themes')]
    public function test_ak7_hinweis_erscheint_nicht_ohne_verfuegbaren_anbieter(string $theme): void
    {
        config()->set('userauth.theme', $theme);
        $this->hinweisAn();
        $this->get(route('login'))->assertSee(self::HINWEIS_DE, false);

        $this->provider->markNotConfigured();
        $this->get(route('login'))->assertOk()->assertDontSee(self::HINWEIS_DE, false);

        $this->provider = new FakeSocialIdentityProvider;
        $this->app->instance(SocialIdentityProvider::class, $this->provider);
        $this->get(route('login'))->assertSee(self::HINWEIS_DE, false);

        config()->set('userauth.social.providers', []);
        $this->get(route('login'))->assertOk()->assertDontSee(self::HINWEIS_DE, false);
    }

    // AK7
    #[DataProvider('themes')]
    public function test_ak7_hinweis_erscheint_nicht_wenn_social_login_aus_ist(string $theme): void
    {
        config()->set('userauth.theme', $theme);
        $this->hinweisAn();
        $this->get(route('login'))->assertSee(self::HINWEIS_DE, false);

        config()->set('userauth.social.enabled', false);

        $this->get(route('login'))->assertOk()->assertDontSee(self::HINWEIS_DE, false);
    }

    // AK7
    public function test_ak7_hinweis_ist_auch_auf_englisch_uebersetzt(): void
    {
        $this->hinweisAn();
        app()->setLocale('en');

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringNotContainsString(self::HINWEIS_DE, $html);
        $this->assertStringNotContainsString('userauth::', $html, 'kein roher Übersetzungsschlüssel');
        $this->assertMatchesRegularExpression('/deleted/i', $html);
        $this->assertMatchesRegularExpression('/support/i', $html);

        // Gegenprobe: ohne die Option neu steht nichts davon auf der englischen Seite.
        $this->option('sperren');
        $this->assertDoesNotMatchRegularExpression('/deleted/i', $this->get(route('login'))->assertOk()->getContent());
    }

    // AK7
    #[DataProvider('themes')]
    public function test_ak7_hinweis_nennt_keine_konkrete_email(string $theme): void
    {
        config()->set('userauth.theme', $theme);
        $this->hinweisAn();
        $this->geloescht();
        $aktiv = User::factory()->create(['email' => 'aktiv@example.test', 'role_id' => 3]);

        $html = $this->get(route('login').'?email='.self::EMAIL)->assertOk()->assertSee(self::HINWEIS_DE, false)->getContent();

        $this->assertStringNotContainsString(self::EMAIL, $html);
        $this->assertStringNotContainsString($aktiv->email, $html);
        // Gleiche Seite mit und ohne gelöschtes Konto: keine Auskunft über dessen Existenz.
        User::withTrashed()->where('email', self::EMAIL)->forceDelete();
        $ohne = $this->get(route('login').'?email='.self::EMAIL)->getContent();
        $this->assertSame(preg_replace('/name="_token" value="[^"]+"/', '', $ohne), preg_replace('/name="_token" value="[^"]+"/', '', $html));
    }

    // ---------------------------------------------------------------- AK8

    // AK8
    public function test_ak8_wiederherstellen_schreibt_audit_eintrag_user_reactivated(): void
    {
        config()->set('userauth.audit_log_enabled', true);
        $this->option('reaktivieren');
        $alt = $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'user_reactivated')->count());
        $this->assertDatabaseHas('user_auth_audit_logs', ['event' => 'user_reactivated', 'user_id' => $alt->id, 'email' => self::EMAIL]);
        $this->assertSame(0, DB::table('user_auth_audit_logs')->where('event', 'user_purged')->count(), 'kein purge-Eintrag');
    }

    // AK8
    public function test_ak8_endgueltiges_loeschen_schreibt_audit_eintrag_user_purged_mit_alter_id(): void
    {
        config()->set('userauth.audit_log_enabled', true);
        $this->option('neu');
        $alt = $this->geloescht();

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'user_purged')->count());
        $this->assertDatabaseHas('user_auth_audit_logs', ['event' => 'user_purged', 'user_id' => $alt->id, 'email' => self::EMAIL]);
        $this->assertSame(0, DB::table('user_auth_audit_logs')->where('event', 'user_reactivated')->count());
    }

    // AK8
    public function test_ak8_social_reaktivieren_und_neu_schreiben_ebenfalls_eintraege(): void
    {
        config()->set('userauth.audit_log_enabled', true);
        $alt = $this->socialZiel('reaktivieren');
        $this->socialCallback()->assertRedirect('/');
        $this->assertDatabaseHas('user_auth_audit_logs', ['event' => 'user_reactivated', 'user_id' => $alt->id, 'email' => self::EMAIL]);

        $this->app['auth']->guard()->logout();
        User::query()->where('id', $alt->id)->delete();
        $this->option('neu');
        $this->provider->returnIdentity('google-sub-2', self::EMAIL);
        $this->socialCallback()->assertRedirect('/');
        $this->assertDatabaseHas('user_auth_audit_logs', ['event' => 'user_purged', 'user_id' => $alt->id, 'email' => self::EMAIL]);
    }

    // AK8
    public function test_ak8_bei_ausgeschaltetem_audit_log_entsteht_kein_eintrag(): void
    {
        config()->set('userauth.audit_log_enabled', false);
        $this->option('reaktivieren');
        $alt = $this->geloescht();
        $this->anlegen($this->daten())->assertSessionHasNoErrors();
        $this->assertNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: wiederhergestellt');

        $this->option('neu');
        User::query()->where('id', $alt->id)->delete();
        $this->anlegen($this->daten(['email' => self::EMAIL]))->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('users')->where('id', $alt->id)->count(), 'Gegenprobe: endgültig gelöscht');

        $this->assertSame(0, DB::table('user_auth_audit_logs')->whereIn('event', ['user_reactivated', 'user_purged'])->count());
    }

    // ---------------------------------------------------------------- Randfälle

    // Randfall 1
    public function test_randfall1_reaktivieren_nimmt_die_zuletzt_geloeschte_zeile(): void
    {
        DB::statement('DROP INDEX users_email_unique');
        $this->option('reaktivieren');
        $aelter = $this->geloescht([], false);
        $juenger = $this->geloescht([], false);
        DB::table('users')->where('id', $aelter->id)->update(['deleted_at' => now()->subDays(10)]);
        DB::table('users')->where('id', $juenger->id)->update(['deleted_at' => now()->subDays(1)]);

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertNull($this->rohzeile($juenger->id)['deleted_at'], 'die zuletzt gelöschte wird wiederhergestellt');
        $this->assertNotNull($this->rohzeile($aelter->id)['deleted_at'], 'die übrige bleibt gelöscht');
        $this->assertSame(2, $this->benutzerAnzahl());
    }

    // Randfall 1
    public function test_randfall1_neu_loescht_alle_geloeschten_zeilen_endgueltig(): void
    {
        DB::statement('DROP INDEX users_email_unique');
        $this->option('neu');
        $erste = $this->geloescht();
        $zweite = $this->geloescht([], false);
        $this->assertSame(2, $this->benutzerAnzahl(), 'Ausgangslage');

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('users')->whereIn('id', [$erste->id, $zweite->id])->count());
        $this->assertSame(1, $this->benutzerAnzahl(), 'nur die neue Zeile');
        $this->assertNull(DB::table('users')->where('email', self::EMAIL)->value('deleted_at'));
    }

    // Randfall 3
    public function test_randfall3_geloeschte_rolle_des_alten_benutzers_ist_beim_reaktivieren_irrelevant(): void
    {
        $this->option('reaktivieren');
        DB::table('roles')->insert(['id' => 4, 'role' => 'alt', 'role_display' => 'Alt', 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $alt = $this->geloescht(['role_id' => 4]);

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $zeile = $this->rohzeile($alt->id);
        $this->assertNull($zeile['deleted_at']);
        $this->assertSame(self::ROLLE_FORMULAR, (int) $zeile['role_id'], 'Rolle aus dem Formular');
    }

    // Randfall 3
    public function test_randfall3_geloeschte_rolle_des_alten_benutzers_ist_bei_social_reaktivierung_irrelevant(): void
    {
        config()->set('userauth.social.allow_registration', true);
        $this->option('reaktivieren');
        DB::table('roles')->insert(['id' => 4, 'role' => 'alt', 'role_display' => 'Alt', 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $alt = $this->geloescht(['role_id' => 4]);
        $this->provider->returnIdentity('google-sub-1', self::EMAIL);

        $this->socialCallback()->assertRedirect('/');

        $zeile = $this->rohzeile($alt->id);
        $this->assertNull($zeile['deleted_at']);
        $this->assertSame(3, (int) $zeile['role_id'], 'Rolle aus social.default_role');
    }

    // ---------------------------------------------------------------- Randfall 4

    private const ALTES_BILD = 'users/alt-randfall4.jpg';

    private function geloeschtMitBilddatei(): User
    {
        Storage::fake('public');
        Storage::disk('public')->put(self::ALTES_BILD, 'bilddaten');

        return $this->geloescht(['image' => 'storage/'.self::ALTES_BILD]);
    }

    // Randfall 4
    public function test_randfall4_neu_loescht_die_bilddatei_der_alten_zeile(): void
    {
        $this->option('neu');
        $alt = $this->geloeschtMitBilddatei();
        $this->assertTrue(Storage::disk('public')->exists(self::ALTES_BILD), 'Ausgangslage: Datei da');

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('users')->where('id', $alt->id)->count(), 'Gegenprobe: alte Zeile weg');
        $this->assertFalse(Storage::disk('public')->exists(self::ALTES_BILD), 'Datei der alten Zeile ist gelöscht');
    }

    // Randfall 4
    public function test_randfall4_reaktivieren_mit_neuem_bild_loescht_die_alte_datei_und_behaelt_die_neue(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloeschtMitBilddatei();

        $this->anlegen($this->daten(['image' => UploadedFile::fake()->image('neu.jpg')]))->assertSessionHasNoErrors();

        $bild = (string) $this->rohzeile($alt->id)['image'];
        $this->assertNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $this->assertNotSame('storage/'.self::ALTES_BILD, $bild);
        $this->assertTrue(Storage::disk('public')->exists(substr($bild, strlen('storage/'))), 'neue Datei da');
        $this->assertFalse(Storage::disk('public')->exists(self::ALTES_BILD), 'alte Datei ist gelöscht');
    }

    // Randfall 4
    public function test_randfall4_reaktivieren_ohne_bild_loescht_die_alte_datei(): void
    {
        $this->option('reaktivieren');
        $alt = $this->geloeschtMitBilddatei();

        $this->anlegen($this->daten())->assertSessionHasNoErrors();

        $this->assertNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $this->assertEmpty($this->rohzeile($alt->id)['image'], 'Gegenprobe: keine Zeile verweist mehr auf die Datei');
        $this->assertFalse(Storage::disk('public')->exists(self::ALTES_BILD), 'alte Datei ist gelöscht');
    }

    // Randfall 4
    public function test_randfall4_scheitert_reaktivieren_bleibt_die_bilddatei(): void
    {
        $this->option('reaktivieren');
        config()->set('userauth.password_policy.enabled', true);
        config()->set('userauth.password_policy.prevent_reuse_last_passwords', 3);
        $alt = $this->geloeschtMitBilddatei();
        DB::table('users')->where('id', $alt->id)->whereNotNull('deleted_at')->update(['password_changed_at' => now()->subDays(10)]);

        $antwort = $this->anlegen($this->daten(['password' => self::ALTES_PASSWORT, 'password2' => self::ALTES_PASSWORT]));

        $antwort->assertSessionHasErrors('password');
        $this->assertNotNull($this->rohzeile($alt->id)['deleted_at'], 'Gegenprobe: Vorgang ist gescheitert');
        $this->assertTrue(Storage::disk('public')->exists(self::ALTES_BILD), 'Datei bleibt');
    }

    // Randfall 4
    public function test_randfall4_scheitert_die_neuanlage_bei_neu_bleibt_die_bilddatei(): void
    {
        $this->option('neu');
        $alt = $this->geloeschtMitBilddatei();
        $aufgerufen = 0;
        User::creating(function () use (&$aufgerufen): void {
            $aufgerufen++;
            throw new RuntimeException('Neuanlage scheitert');
        });

        $this->anlegen($this->daten());

        $this->assertGreaterThan(0, $aufgerufen, 'Gegenprobe: die Neuanlage wurde versucht und ist gescheitert');
        $this->assertSame(1, DB::table('users')->where('id', $alt->id)->count(), 'alte Zeile bleibt');
        $this->assertTrue(Storage::disk('public')->exists(self::ALTES_BILD), 'Datei bleibt');
    }

    // Randfall 4
    public function test_randfall4_social_reaktivierung_behaelt_bild_datei_und_spalte(): void
    {
        config()->set('userauth.social.allow_registration', true);
        $this->option('reaktivieren');
        $alt = $this->geloeschtMitBilddatei();
        $this->provider->returnIdentity('google-sub-1', self::EMAIL);

        $this->socialCallback()->assertRedirect('/');

        $zeile = $this->rohzeile($alt->id);
        $this->assertNull($zeile['deleted_at'], 'Gegenprobe: wiederhergestellt');
        $this->assertSame('storage/'.self::ALTES_BILD, $zeile['image'], 'Spalte bleibt');
        $this->assertTrue(Storage::disk('public')->exists(self::ALTES_BILD), 'Datei bleibt');
    }

    // Randfall 4
    public function test_randfall4_sperren_laesst_die_bilddatei_unberuehrt(): void
    {
        $this->option('sperren');
        $alt = $this->geloeschtMitBilddatei();

        $this->anlegen($this->daten())->assertSessionHasErrors('email');

        $this->assertSame(1, DB::table('users')->where('id', $alt->id)->count(), 'Gegenprobe: Vorgang wurde abgelehnt');
        $this->assertTrue(Storage::disk('public')->exists(self::ALTES_BILD), 'Datei bleibt');
    }
}
