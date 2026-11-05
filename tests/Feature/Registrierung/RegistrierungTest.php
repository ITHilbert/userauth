<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Registrierung;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\RegistrierungHilfen;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;

/**
 * Selbstregistrierung (userauth-registrierung, Dashboard #255): Schalter, Formular, Konto anlegen, Rolle,
 * Passwortregeln, doppelte E-Mail, Rate-Limit, Audit `registered`, bestehende Konten, toter Code, README.
 *
 * Standardzustand der Tests: Registrierung an, Rolle `user`, Locale `de`. Andere Zustände starten die App neu.
 */
final class RegistrierungTest extends TestCase
{
    use RefreshDatabase;
    use RegistrierungHilfen;

    private const EMAIL = 'kunde@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->grundeinstellung();
        $this->rollenAnlegen();
    }

    private function paketPfad(string $relativ = ''): string
    {
        return dirname(__DIR__, 3).($relativ === '' ? '' : '/'.$relativ);
    }

    // ------------------------------------------------------------------- AK1

    /**
     * @return array<string, array{mixed}>
     */
    public static function ausZustaende(): array
    {
        return [
            'nicht gesetzt (Paket-Vorgabe)' => [['paketstandard' => true]],
            'false' => [['enabled' => false]],
            'leer gesetzt' => [['enabled' => '']],
            'Eintrag fehlt' => [['enabled' => self::NICHT_GESETZT]],
            'null' => [['enabled' => null]],
        ];
    }

    /**
     * @param  array<string, mixed>  $konfiguration
     */
    // AK1
    #[DataProvider('ausZustaende')]
    public function test_ak1_schalter_aus_register_antwortet_auf_get_und_post_mit_404(array $konfiguration): void
    {
        $this->get('/register')->assertOk(); // Gegenprobe: bei „an“ erreichbar

        $this->neuStarten($konfiguration);

        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->gueltigeDaten())->assertNotFound();
        $this->assertSame([], $this->konten(self::EMAIL));
    }

    /**
     * @param  array<string, mixed>  $konfiguration
     */
    // AK1
    #[DataProvider('ausZustaende')]
    public function test_ak1_schalter_aus_bestaetigungs_und_erneut_anfordern_route_antworten_mit_404(array $konfiguration): void
    {
        $link = $this->registriertMitLink();
        $this->assertNotSame(404, $this->get($link)->getStatusCode(), 'Gegenprobe: Bei „an“ darf der Link nicht 404 liefern.');
        $pfade = [];
        foreach ($this->registrierungsRouten() as $route) {
            if (! str_contains($route->uri(), '{')) {
                $pfade[] = '/'.ltrim($route->uri(), '/');
            }
        }
        $this->assertNotEmpty($pfade, 'Es gibt keine Route „Link erneut anfordern“.');
        $erneut = '/'.ltrim($this->erneutAnfordernRoute()->uri(), '/');
        $this->assertNotSame(404, $this->post($erneut, ['email' => self::EMAIL])->getStatusCode(), 'Gegenprobe: Bei „an“ darf die Route nicht 404 liefern.');
        $zweiter = $this->registriertMitLink('zweiter@example.test');

        $this->neuStarten($konfiguration);

        $this->get($zweiter)->assertNotFound();
        $this->post($zweiter)->assertNotFound();
        foreach ($pfade as $pfad) {
            $this->get($pfad)->assertNotFound();
            $this->post($pfad, ['email' => 'zweiter@example.test'])->assertNotFound();
        }
        $this->assertSame([], $this->gesendeteNachrichten());
        $this->assertNull($this->konto('zweiter@example.test')->email_verified_at);
    }

    /**
     * @param  array<string, mixed>  $konfiguration
     */
    // AK1
    #[DataProvider('ausZustaende')]
    public function test_ak1_schalter_aus_login_seite_zeigt_in_beiden_themes_keinen_link_zur_registrierung(array $konfiguration): void
    {
        foreach (['tailwind', 'bootstrap'] as $theme) {
            config()->set('userauth.theme', $theme);
            $this->assertTrue($this->hatRegisterLink((string) $this->get('/login')->assertOk()->getContent()), "Gegenprobe ({$theme}): Bei „an“ zeigt die Login-Seite den Link.");
        }

        $this->neuStarten($konfiguration);

        foreach (['tailwind', 'bootstrap'] as $theme) {
            config()->set('userauth.theme', $theme);
            $this->assertFalse($this->hatRegisterLink((string) $this->get('/login')->assertOk()->getContent()), "Login-Seite ({$theme}) zeigt einen Link zur Registrierung.");
        }
    }

    /**
     * Pfade der Registrierung (ohne Pfadparameter) aus dem Zustand „an“.
     *
     * @return list<string>
     */
    private function registrierungsPfade(): array
    {
        $pfade = ['/register'];
        foreach ($this->registrierungsRouten() as $route) {
            if (! str_contains($route->uri(), '{')) {
                $pfade[] = '/'.ltrim($route->uri(), '/');
            }
        }

        return array_values(array_unique($pfade));
    }

    private function fallbackRouteRegistrieren(): void
    {
        Route::fallback(fn () => abort(404));
    }

    /**
     * @param  list<string>  $pfade
     */
    private function assertAllesAntwortetMit404(array $pfade, string $link): void
    {
        foreach ($pfade as $pfad) {
            $this->get($pfad)->assertNotFound();
            $this->post($pfad, $this->gueltigeDaten())->assertNotFound();
        }
        $this->get($link)->assertNotFound();
        $this->post($link)->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $konfiguration
     */
    // AK1
    #[DataProvider('ausZustaende')]
    public function test_ak1_schalter_aus_ergibt_mit_get_fallback_route_fuer_jede_methode_404(array $konfiguration): void
    {
        $pfade = $this->registrierungsPfade();
        $link = $this->registriertMitLink();
        $this->fallbackRouteRegistrieren();
        $this->get('/register')->assertOk(); // Gegenprobe: bei „an“ erreichbar, auch mit Fallback-Route
        $this->assertNotSame(405, $this->post('/register', $this->gueltigeDaten())->getStatusCode());

        $this->neuStarten($konfiguration);
        $this->fallbackRouteRegistrieren();

        $this->assertAllesAntwortetMit404($pfade, $link);
        $this->assertCount(1, $this->konten(self::EMAIL), 'Es darf kein weiteres Konto entstehen.');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function ausWerte(): array
    {
        return [
            'false' => [false],
            'leer gesetzt' => [''],
            'null (nicht gesetzt)' => [null],
        ];
    }

    // AK1
    #[DataProvider('ausWerte')]
    public function test_ak1_ausschalten_wirkt_zur_laufzeit_ohne_neue_routenregistrierung(mixed $aus): void
    {
        $pfade = $this->registrierungsPfade();
        $link = $this->registriertMitLink();
        $this->fallbackRouteRegistrieren();
        $this->get('/register')->assertOk(); // Gegenprobe: Routen sind mit „an“ registriert

        config()->set('userauth.registration.enabled', $aus);

        $this->assertAllesAntwortetMit404($pfade, $link);
        $this->assertFalse($this->hatRegisterLink((string) $this->get('/login')->assertOk()->getContent()), 'Login-Seite zeigt noch den Link.');
        $this->assertNull($this->konto(self::EMAIL)->email_verified_at, 'Der Link darf nichts bestätigen.');
        config()->set('userauth.registration.enabled', true);
        $this->get('/register')->assertOk();
    }

    /**
     * @param  array<string, mixed>  $konfiguration
     */
    // AK1
    #[DataProvider('ausZustaende')]
    public function test_ak1_einschalten_wirkt_zur_laufzeit_ohne_neue_routenregistrierung(array $konfiguration): void
    {
        $pfade = $this->registrierungsPfade();
        $link = $this->registriertMitLink();
        $this->neuStarten($konfiguration); // Routen werden mit „aus“ registriert
        $this->fallbackRouteRegistrieren();
        foreach ($pfade as $pfad) {
            $this->get($pfad)->assertNotFound(); // Gegenprobe: zuerst aus
        }
        $this->get($link)->assertNotFound();

        config()->set('userauth.registration.enabled', true);

        $this->get('/register')->assertOk();
        $this->assertTrue($this->hatRegisterLink((string) $this->get('/login')->assertOk()->getContent()));
        $this->registrieren(['email' => 'neu@example.test'])->assertRedirect();
        $this->assertCount(1, $this->konten('neu@example.test'));
        $this->get($link);
        $this->assertNotNull($this->konto(self::EMAIL)->email_verified_at, 'Der Link bestätigt nach dem Einschalten.');
    }

    private function hatRegisterLink(string $html): bool
    {
        foreach ($this->verweiseDerSeite($html) as $ziel) {
            if (str_ends_with((string) parse_url($ziel, PHP_URL_PATH), '/register')) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------- AK2

    /**
     * @return array<string, array{string}>
     */
    public static function themes(): array
    {
        return ['tailwind' => ['tailwind'], 'bootstrap' => ['bootstrap']];
    }

    // AK2
    #[DataProvider('themes')]
    public function test_ak2_formular_hat_name_email_passwort_und_wiederholung(string $theme): void
    {
        config()->set('userauth.theme', $theme);

        $html = (string) $this->get('/register')->assertOk()->getContent();

        $felder = $this->formularFelder($html, '/register');
        foreach (['name', 'email', 'password', 'password_confirmation'] as $name) {
            $this->assertArrayHasKey($name, $felder, "Das Formular hat kein Feld {$name}.");
        }
        $this->assertMatchesRegularExpression('~<input[^>]*name="password"[^>]*type="password"|<input[^>]*type="password"[^>]*name="password"~', $html);
        $this->assertMatchesRegularExpression('~<input[^>]*name="password_confirmation"[^>]*type="password"|<input[^>]*type="password"[^>]*name="password_confirmation"~', $html);
    }

    // AK2
    #[DataProvider('themes')]
    public function test_ak2_formular_laedt_dieselben_stile_und_skripte_wie_die_login_seite_des_themes(string $theme): void
    {
        config()->set('userauth.theme', $theme);
        config()->set('userauth.auth_assets', ['css' => ['assets/css/theme.min.css'], 'js' => ['assets/js/config.js']]);

        $login = (string) $this->get('/login')->assertOk()->getContent();
        $register = (string) $this->get('/register')->assertOk()->getContent();

        $quellen = static function (string $html): array {
            preg_match_all('~<link[^>]*rel="stylesheet"[^>]*href="([^"]+)"|<link[^>]*href="([^"]+)"[^>]*rel="stylesheet"|<script[^>]*src="([^"]+)"~i', $html, $t);
            $alle = array_filter(array_merge($t[1], $t[2], $t[3]), static fn (string $q): bool => $q !== '');
            sort($alle);

            return array_values(array_unique($alle));
        };

        $this->assertNotEmpty($quellen($login), 'Gegenprobe: Die Login-Seite lädt Stile.');
        $this->assertSame($quellen($login), $quellen($register));
    }

    // AK2
    public function test_ak2_texte_kommen_aus_sprachdateien_de_und_en(): void
    {
        app()->setLocale('de');
        $de = $this->seitentext((string) $this->get('/register')->assertOk()->getContent());
        app()->setLocale('en');
        $en = $this->seitentext((string) $this->get('/register')->assertOk()->getContent());

        $this->assertNotSame($de, $en, 'Die Seite ist in de und en gleich.');
        $this->assertStringNotContainsString('userauth::', $de);
        $this->assertStringNotContainsString('userauth::', $en);
        $this->assertDoesNotMatchRegularExpression('~\b[a-z_]+\.[a-z_]+\.[a-z_]+\b~', $de, 'Ein Sprachschlüssel wird als Text angezeigt (de).');
        $this->assertDoesNotMatchRegularExpression('~\b[a-z_]+\.[a-z_]+\.[a-z_]+\b~', $en, 'Ein Sprachschlüssel wird als Text angezeigt (en).');
    }

    // AK2
    #[DataProvider('themes')]
    public function test_ak2_login_seite_zeigt_den_link_registrieren_zum_formular(string $theme): void
    {
        config()->set('userauth.theme', $theme);

        $html = (string) $this->get('/login')->assertOk()->getContent();

        $this->assertTrue($this->hatRegisterLink($html), 'Kein Link auf /register.');
        $this->assertStringContainsString('Registrieren', $html);
    }

    // AK2
    public function test_ak2_angemeldeter_nutzer_wird_beim_formular_wie_beim_login_weitergeleitet(): void
    {
        $this->get('/register')->assertOk(); // Gegenprobe: Gast sieht das Formular
        config()->set('userauth.redirect_after_login', '/ziel-nach-login');
        $nutzer = User::factory()->create();
        $this->actingAs($nutzer);

        $login = $this->get('/login');
        $register = $this->get('/register');

        $login->assertRedirect();
        $register->assertRedirect($login->headers->get('Location'));
    }

    // AK2
    public function test_ak2_angemeldeter_nutzer_wird_beim_absenden_weitergeleitet_und_legt_kein_konto_an(): void
    {
        $nutzer = User::factory()->create(['email' => 'angemeldet@example.test']);
        $this->actingAs($nutzer);
        $login = $this->get('/login');
        $login->assertRedirect();

        $antwort = $this->registrieren();

        $antwort->assertRedirect($login->headers->get('Location'));
        $this->assertSame([], $this->konten(self::EMAIL));
        $this->assertCount(1, DB::table('users')->get(), 'Gegenprobe: Der angemeldete Nutzer ist das einzige Konto.');
    }

    // ------------------------------------------------------------------- AK3

    // AK3
    public function test_ak3_es_entsteht_genau_ein_konto_mit_name_und_getrimmter_email(): void
    {
        $vorher = DB::table('users')->count();

        $this->registrieren(['name' => 'Erika Kunde', 'email' => '  '.self::EMAIL.'  ']);

        $this->assertSame($vorher + 1, DB::table('users')->count());
        $konto = $this->konten(self::EMAIL);
        $this->assertCount(1, $konto);
        $this->assertSame('Erika Kunde', $konto[0]->name);
        $this->assertSame(self::EMAIL, $konto[0]->email);
    }

    // AK3
    public function test_ak3_passwort_wird_gehasht_gespeichert(): void
    {
        $this->registrieren();

        $konto = $this->konto(self::EMAIL);
        $this->assertNotSame(self::PASSWORT, $konto->password);
        $this->assertTrue(Hash::check(self::PASSWORT, $konto->password));
    }

    // AK3
    public function test_ak3_konto_hat_die_rolle_aus_der_konfiguration(): void
    {
        $this->registrieren();

        $this->assertSame(3, (int) $this->konto(self::EMAIL)->role_id);
    }

    // AK3
    public function test_ak3_email_ist_noch_nicht_bestaetigt(): void
    {
        $this->registrieren();

        $this->assertNotEmpty($this->konten(self::EMAIL));
        $this->assertNull($this->konto(self::EMAIL)->email_verified_at);
    }

    // AK3
    public function test_ak3_nutzer_ist_danach_nicht_angemeldet(): void
    {
        $antwort = $this->registrieren();

        $antwort->assertRedirect();
        $this->assertCount(1, $this->konten(self::EMAIL), 'Gegenprobe: Das Konto existiert.');
        $this->assertGuest();
        $this->get('/password/edit')->assertRedirect(route('login'));
    }

    // AK3
    public function test_ak3_weiterleitung_fuehrt_auf_eine_seite_mit_der_meldung_ueber_die_mail(): void
    {
        $antwort = $this->registrieren();

        $antwort->assertRedirect();
        $seite = $this->get($antwort->headers->get('Location'));
        $seite->assertOk();
        $this->assertStringContainsString('Wir haben Ihnen eine E-Mail geschickt', $this->seitentext((string) $seite->getContent()));
    }

    // AK3
    #[DataProvider('themes')]
    public function test_ak3_formular_wie_der_browser_absenden_legt_das_konto_an(string $theme): void
    {
        config()->set('userauth.theme', $theme);
        $felder = $this->formularFelder((string) $this->get('/register')->assertOk()->getContent(), '/register');
        $this->assertNotEmpty($felder, 'Gegenprobe: Das Formular wurde gefunden.');
        $felder = array_merge($felder, [
            'name' => 'Erika Kunde',
            'email' => self::EMAIL,
            'password' => self::PASSWORT,
            'password_confirmation' => self::PASSWORT,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.200'])->post('/register', $felder)->assertRedirect();

        $this->assertCount(1, $this->konten(self::EMAIL));
    }

    // ------------------------------------------------------------------- AK4

    // AK4
    public function test_ak4_rolle_user_wird_vergeben(): void
    {
        $this->neuStarten(['role' => 'user']);

        $this->registrieren();

        $this->assertSame(3, (int) $this->konto(self::EMAIL)->role_id);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function ohneRolle(): array
    {
        return ['nicht gesetzt' => [self::NICHT_GESETZT], 'leer gesetzt' => ['']];
    }

    // AK4
    #[DataProvider('ohneRolle')]
    public function test_ak4_ohne_rollen_konfiguration_hat_das_konto_keine_rolle(mixed $wert): void
    {
        $this->assertSame(3, DB::table('roles')->count(), 'Gegenprobe: Rollen existieren.');
        $this->neuStarten(['role' => $wert]);

        $this->registrieren()->assertRedirect();

        $konten = $this->konten(self::EMAIL);
        $this->assertCount(1, $konten);
        $this->assertNull($konten[0]->role_id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unzulaessigeRollen(): array
    {
        return [
            'Rolle existiert nicht' => ['gibt-es-nicht'],
            'privilegiert dev' => ['dev'],
            'privilegiert admin' => ['admin'],
        ];
    }

    // AK4
    #[DataProvider('unzulaessigeRollen')]
    public function test_ak4_unzulaessige_rolle_legt_kein_konto_an_und_meldet_konfigurationsfehler(string $rolle): void
    {
        $this->assertSame(3, DB::table('roles')->count(), 'Gegenprobe: dev, admin und user existieren.');
        $this->neuStarten(['role' => $rolle]);
        $this->withoutExceptionHandling();

        $ausnahme = null;
        try {
            $this->registrieren();
        } catch (Throwable $e) {
            $ausnahme = $e;
        }

        $this->assertNotNull($ausnahme, 'Erwartet eine Exception wegen falscher Konfiguration.');
        $this->assertStringContainsString('userauth.registration.role', $ausnahme->getMessage());
        $this->assertSame([], $this->konten(self::EMAIL));
        $this->assertSame([], $this->gesendeteNachrichten());
    }

    // AK4
    #[DataProvider('unzulaessigeRollen')]
    public function test_ak4_unzulaessige_rolle_ohne_ausnahmebehandlung_ergibt_serverfehler_statt_konto(string $rolle): void
    {
        $this->neuStarten(['role' => $rolle]);

        $antwort = $this->registrieren();

        $this->assertSame(500, $antwort->getStatusCode());
        $this->assertSame([], $this->konten(self::EMAIL));
    }

    // ------------------------------------------------------------------- AK5

    /**
     * @return array<string, array{array<string, mixed>, list<string>, list<string>}> Felder, ohne, Fehlerfelder (eines davon)
     */
    public static function ungueltigeEingaben(): array
    {
        return [
            'Passwort zu kurz' => [['password' => 'Ab1!xyz', 'password_confirmation' => 'Ab1!xyz'], [], ['password']],
            'Wiederholung weicht ab' => [['password_confirmation' => 'Anderes-Passwort-2026!'], [], ['password', 'password_confirmation']],
            'Wiederholung fehlt' => [[], ['password_confirmation'], ['password', 'password_confirmation']],
            'Passwort fehlt' => [[], ['password', 'password_confirmation'], ['password']],
            'Passwort leer' => [['password' => '', 'password_confirmation' => ''], [], ['password']],
            'Name fehlt' => [[], ['name'], ['name']],
            'Name leer' => [['name' => ''], [], ['name']],
            'E-Mail fehlt' => [[], ['email'], ['email']],
            'E-Mail leer' => [['email' => ''], [], ['email']],
            'E-Mail ungültig' => [['email' => 'keine-adresse'], [], ['email']],
            'E-Mail ungültig mit @' => [['email' => 'a@'], [], ['email']],
        ];
    }

    /**
     * @param  array<string, mixed>  $felder
     * @param  list<string>  $ohne
     * @param  list<string>  $fehlerfelder
     */
    // AK5
    #[DataProvider('ungueltigeEingaben')]
    public function test_ak5_ungueltige_eingabe_ergibt_fehler_am_feld_kein_konto_keine_mail(array $felder, array $ohne, array $fehlerfelder): void
    {
        $this->registrieren($felder, '198.51.100.99', $ohne)->assertRedirect();

        $fehler = session('errors');
        $this->assertNotNull($fehler);
        $this->assertNotEmpty(array_filter($fehlerfelder, static fn (string $feld): bool => $fehler->has($feld)), 'Kein Fehler an '.implode(' oder ', $fehlerfelder));
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame([], $this->gesendeteNachrichten());
        // Gegenprobe: mit gültigen Daten entsteht das Konto in demselben Aufbau
        $this->registrieren(['email' => self::EMAIL], '198.51.100.98')->assertSessionHasNoErrors();
        $this->assertCount(1, $this->konten(self::EMAIL));
    }

    // AK5
    #[DataProvider('ungueltigeEingaben')]
    public function test_ak5_fehler_steht_in_der_seite(array $felder, array $ohne, array $fehlerfelder): void
    {
        $this->registrieren($felder, '198.51.100.97', $ohne)->assertRedirect();
        $meldungen = session('errors')->all();
        $this->assertNotEmpty($meldungen);

        $zweite = $this->registrieren($felder, '198.51.100.97', $ohne)->assertRedirect();
        $seite = $this->get($zweite->headers->get('Location'));

        $seite->assertOk();
        foreach ($meldungen as $meldung) {
            $seite->assertSee(e($meldung), false);
        }
    }

    // AK5
    #[DataProvider('felder')]
    public function test_ak5_liste_statt_einzelwert_ergibt_fehler_am_feld_ohne_serverfehler(string $feld): void
    {
        $antwort = $this->registrieren([$feld => ['a', 'b']], '198.51.100.96');

        $antwort->assertRedirect();
        $this->assertTrue(session('errors')->has($feld) || session('errors')->has('password'), "Kein Fehler am Feld {$feld}.");
        $this->get($antwort->headers->get('Location'))->assertOk();
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame([], $this->gesendeteNachrichten());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function felder(): array
    {
        return [
            'name' => ['name'],
            'email' => ['email'],
            'password' => ['password'],
            'password_confirmation' => ['password_confirmation'],
        ];
    }

    // AK5
    public function test_ak5_mit_passwort_richtlinie_gilt_das_konto_nicht_als_abgelaufen(): void
    {
        config()->set('userauth.password_policy.enabled', true);
        config()->set('userauth.password_policy.require_change_every_days', 90);
        $this->travelTo(now()->startOfSecond());

        $this->registrieren();

        $konto = $this->konto(self::EMAIL);
        $this->assertNotNull($konto->password_changed_at, 'Der Änderungszeitpunkt des Passworts ist nicht gesetzt.');
        $this->assertLessThanOrEqual(5, abs(now()->diffInSeconds(Carbon::parse($konto->password_changed_at))));
    }

    // ------------------------------------------------------------------ AK11

    /**
     * @return array<string, array{string, string, bool}> bestehende E-Mail, Eingabe, soft-gelöscht
     */
    public static function bestehendeKonten(): array
    {
        return [
            'aktiv, gleiche Schreibweise' => ['kunde@example.test', 'kunde@example.test', false],
            'aktiv, andere Schreibweise' => ['kunde@example.test', 'KUNDE@Example.TEST', false],
            'Randfall 1: Kunde@X.de gegen kunde@x.de' => ['kunde@x.de', 'Kunde@X.de', false],
            'Randfall 1 umgekehrt' => ['Kunde@X.de', 'kunde@x.de', false],
            'soft-gelöscht, gleiche Schreibweise' => ['kunde@example.test', 'kunde@example.test', true],
            'soft-gelöscht, andere Schreibweise' => ['kunde@example.test', 'Kunde@Example.test', true],
        ];
    }

    // AK11
    #[DataProvider('bestehendeKonten')]
    public function test_ak11_doppelte_email_legt_kein_zweites_konto_an_und_laesst_das_bestehende_unveraendert(string $bestehend, string $eingabe, bool $geloescht): void
    {
        $konto = User::factory()->create(['email' => $bestehend, 'name' => 'Bestand', 'role_id' => 3]);
        if ($geloescht) {
            $konto->delete();
        }
        $this->registrieren(['email' => 'frisch@example.test'], '198.51.100.31');
        $this->assertCount(1, $this->konten('frisch@example.test'), 'Gegenprobe: Eine frische Adresse legt ein Konto an.');
        $vorher = DB::table('users')->where('id', $konto->id)->first();
        $anzahl = DB::table('users')->count();

        $this->registrieren(['email' => $eingabe, 'name' => 'Angreifer', 'password' => 'Anderes-Passwort-2026!', 'password_confirmation' => 'Anderes-Passwort-2026!'], '198.51.100.32');

        $this->assertSame($anzahl, DB::table('users')->count());
        $this->assertCount(1, $this->konten($bestehend));
        $this->assertEquals($vorher, DB::table('users')->where('id', $konto->id)->first());
    }

    // AK11
    #[DataProvider('bestehendeKonten')]
    public function test_ak11_antwort_ist_dieselbe_wie_bei_einer_neuen_adresse(string $bestehend, string $eingabe, bool $geloescht): void
    {
        $konto = User::factory()->create(['email' => $bestehend, 'role_id' => 3]);
        if ($geloescht) {
            $konto->delete();
        }
        $neu = $this->antwortMerkmale($this->registrieren(['email' => 'frisch@example.test'], '198.51.100.41'));
        $this->assertNotNull($neu['ziel'], 'Gegenprobe: Eine neue Adresse führt zu einer Weiterleitung.');

        $doppelt = $this->antwortMerkmale($this->registrieren(['email' => $eingabe], '198.51.100.42'));

        $this->assertSame($neu['status'], $doppelt['status']);
        $this->assertSame($neu['ziel'], $doppelt['ziel']);
        $this->assertSame($neu['seite'], $doppelt['seite']);
    }

    // AK11
    #[DataProvider('bestehendeKonten')]
    public function test_ak11_an_die_adresse_geht_keine_bestaetigungsmail(string $bestehend, string $eingabe, bool $geloescht): void
    {
        $konto = User::factory()->create(['email' => $bestehend, 'role_id' => 3]);
        if ($geloescht) {
            $konto->delete();
        }
        $this->registrieren(['email' => 'frisch@example.test'], '198.51.100.51');
        $this->assertCount(1, $this->nachrichtenAn('frisch@example.test'), 'Gegenprobe: Eine neue Adresse erhält genau eine Mail.');

        $this->registrieren(['email' => $eingabe], '198.51.100.52');

        $this->assertSame([], $this->nachrichtenAn($bestehend));
        $this->assertSame([], $this->nachrichtenAn($eingabe));
        $this->assertCount(1, $this->gesendeteNachrichten());
    }

    // AK11
    public function test_ak11_zweite_registrierung_mit_offener_bestaetigung_legt_kein_konto_an_und_schickt_keine_zweite_mail(): void
    {
        $this->registrieren(['name' => 'Erste'], '198.51.100.61');
        $erstes = $this->konto(self::EMAIL);
        $this->assertCount(1, $this->nachrichtenAn(self::EMAIL));

        $this->registrieren(['email' => strtoupper(self::EMAIL), 'name' => 'Zweite'], '198.51.100.62');

        $this->assertCount(1, $this->konten(self::EMAIL));
        $this->assertEquals($erstes, $this->konto(self::EMAIL));
        $this->assertCount(1, $this->gesendeteNachrichten());
    }

    // ------------------------------------------------------------------ AK12

    // AK12
    public function test_ak12_sechste_anfrage_pro_minute_ergibt_429_und_kein_weiteres_konto(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertNotSame(429, $this->registrieren(['email' => "k{$i}@example.test"], '203.0.113.7')->getStatusCode(), "Anfrage {$i} darf nicht gedrosselt werden.");
        }
        $this->assertSame(5, DB::table('users')->count(), 'Gegenprobe: Fünf Konten entstanden.');

        $this->registrieren(['email' => 'k6@example.test'], '203.0.113.7')->assertStatus(429);

        $this->assertSame(5, DB::table('users')->count());
        $this->assertSame([], $this->konten('k6@example.test'));
    }

    // AK12
    public function test_ak12_auch_abgelehnte_anfragen_zaehlen_mit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertNotSame(429, $this->registrieren(['email' => 'ungueltig'], '203.0.113.8')->getStatusCode());
        }

        $this->registrieren(['email' => 'k6@example.test'], '203.0.113.8')->assertStatus(429);
        $this->assertSame([], $this->konten('k6@example.test'));
    }

    // AK12
    public function test_ak12_das_limit_gilt_je_ip(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->registrieren(['email' => "k{$i}@example.test"], '203.0.113.9');
        }
        $this->registrieren(['email' => 'k7@example.test'], '203.0.113.9')->assertStatus(429);

        $andere = $this->registrieren(['email' => 'k8@example.test'], '203.0.113.10');

        $this->assertNotSame(429, $andere->getStatusCode());
        $this->assertCount(1, $this->konten('k8@example.test'));
    }

    // AK12
    public function test_ak12_das_limit_endet_nach_einer_minute(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->registrieren(['email' => "k{$i}@example.test"], '203.0.113.11');
        }
        $this->registrieren(['email' => 'k7@example.test'], '203.0.113.11')->assertStatus(429);
        $this->travel(61)->seconds();

        $this->assertNotSame(429, $this->registrieren(['email' => 'k8@example.test'], '203.0.113.11')->getStatusCode());
        $this->assertCount(1, $this->konten('k8@example.test'));
    }

    // ------------------------------------------------------------------ AK13

    // AK13
    public function test_ak13_registrierung_schreibt_einen_audit_eintrag_registered(): void
    {
        $this->auditTabelleBereitstellen();
        config()->set('userauth.audit_log_enabled', true);
        $vorher = DB::table('user_auth_audit_logs')->count();

        $this->withHeader('User-Agent', 'RegistrierungsTest/1.0');
        $this->registrieren([], '203.0.113.21');

        $eintraege = DB::table('user_auth_audit_logs')->where('event', 'registered')->get();
        $this->assertCount(1, $eintraege);
        $this->assertSame($vorher + 1, DB::table('user_auth_audit_logs')->count());
        $this->assertSame($this->kontoId(self::EMAIL), (int) $eintraege[0]->user_id);
        $this->assertSame(self::EMAIL, $eintraege[0]->email);
        $this->assertSame('203.0.113.21', $eintraege[0]->ip_address);
        $this->assertSame('RegistrierungsTest/1.0', $eintraege[0]->user_agent);
    }

    // AK13
    public function test_ak13_bei_doppelter_email_kein_eintrag_registered(): void
    {
        $this->auditTabelleBereitstellen();
        config()->set('userauth.audit_log_enabled', true);
        $this->registrieren([], '203.0.113.22');
        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'registered')->count(), 'Gegenprobe: Die erste Registrierung schreibt den Eintrag.');

        $this->registrieren(['email' => strtoupper(self::EMAIL)], '203.0.113.23');

        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'registered')->count());
    }

    // AK13
    public function test_ak13_bei_abgelehnter_eingabe_kein_eintrag_registered(): void
    {
        $this->auditTabelleBereitstellen();
        config()->set('userauth.audit_log_enabled', true);

        $this->registrieren(['email' => 'ungueltig'], '203.0.113.24');
        $this->assertSame(0, DB::table('user_auth_audit_logs')->where('event', 'registered')->count());
        $this->registrieren([], '203.0.113.25');

        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'registered')->count(), 'Gegenprobe: Mit gültigen Daten gibt es den Eintrag.');
    }

    // AK13
    public function test_ak13_ist_das_audit_log_aus_gibt_es_keinen_eintrag(): void
    {
        $this->auditTabelleBereitstellen();
        config()->set('userauth.audit_log_enabled', false);

        $this->registrieren([], '203.0.113.26');

        $this->assertCount(1, $this->konten(self::EMAIL), 'Gegenprobe: Das Konto entstand.');
        $this->assertSame(0, DB::table('user_auth_audit_logs')->count());
    }

    // ------------------------------------------------------------------ AK14

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function schalterZustaende(): array
    {
        return [
            'Registrierung an' => [['enabled' => true]],
            'Registrierung aus' => [['enabled' => false]],
        ];
    }

    /**
     * @param  array<string, mixed>  $konfiguration
     */
    // AK14
    #[DataProvider('schalterZustaende')]
    public function test_ak14_vom_admin_angelegtes_konto_ohne_bestaetigung_meldet_sich_an(array $konfiguration): void
    {
        $konto = User::factory()->unverified()->create(['email' => 'admin-angelegt@example.test', 'role_id' => 3]);
        $this->assertNull($konto->fresh()->email_verified_at);
        $this->neuStarten($konfiguration);

        $this->anmelden('admin-angelegt@example.test', 'password')->assertRedirect('/');

        $this->assertAuthenticatedAs($konto->fresh());
    }

    /**
     * @param  array<string, mixed>  $konfiguration
     */
    // AK14
    #[DataProvider('schalterZustaende')]
    public function test_ak14_per_seeder_angelegtes_konto_mit_leerem_verified_at_meldet_sich_an_und_erreicht_geschuetzte_seiten(array $konfiguration): void
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Seeder', 'email' => 'seeder@example.test', 'password' => Hash::make('Seeder-Passwort-2026!'),
            'role_id' => 3, 'email_verified_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->neuStarten($konfiguration);

        $this->anmelden('seeder@example.test', 'Seeder-Passwort-2026!')->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertSame($id, (int) auth()->id());
        $this->get('/password/edit')->assertOk();
    }

    // AK14
    public function test_ak14_bestaetigtes_konto_meldet_sich_an_und_ein_falsches_passwort_wird_weiter_abgelehnt(): void
    {
        $konto = User::factory()->create(['email' => 'bestaetigt@example.test', 'role_id' => 3]);

        $this->anmelden('bestaetigt@example.test', 'falsch')->assertSessionHasErrors();
        $this->assertGuest();
        $this->anmelden('bestaetigt@example.test', 'password')->assertRedirect('/');

        $this->assertAuthenticatedAs($konto->fresh());
    }

    // ------------------------------------------------------------------ AK15

    /**
     * @return array<string, array{string}>
     */
    public static function entfernteDateien(): array
    {
        return [
            'View register' => ['src/Resources/views/auth/register.blade.php'],
            'View verify' => ['src/Resources/views/auth/verify.blade.php'],
            'Schema register' => ['src/Resources/schema/ui/auth/register.json'],
            'Schema verify' => ['src/Resources/schema/ui/auth/verify.json'],
        ];
    }

    // AK15
    #[DataProvider('entfernteDateien')]
    public function test_ak15_toter_code_ist_entfernt(string $datei): void
    {
        $this->assertFileExists($this->paketPfad('src/Resources/views/auth/login.blade.php'), 'Gegenprobe: Der Pfad stimmt (Nachbardatei existiert).');

        $this->assertFileDoesNotExist($this->paketPfad($datei));
    }

    private const VERWEIS_MUSTER = '~auth\.(register|verify)[\'"\s)\]]|userauth::auth\.(register|verify)\b|(^|[^\w])auth/(register|verify)\b|ui/auth/(register|verify)|verification\.resend~i';

    /**
     * @return array<string, array{string, bool}>
     */
    public static function verweisBeispiele(): array
    {
        return [
            'View-Name' => ["return view('userauth::auth.register');", true],
            'View-Name verify' => ["@include('userauth::auth.verify')", true],
            'Pfad der Datei' => ["'src/Resources/views/auth/register.blade.php'", true],
            'Schema-Pfad' => ['schema/ui/auth/verify.json', true],
            'Route der Altlast' => ["route('verification.resend')", true],
            'Punktnotation' => ["view('auth.register')", true],
            'neue Registrierungs-View ist erlaubt' => ["view('userauth::register.tailwind')", false],
            'Login ist erlaubt' => ["view('userauth::auth.login')", false],
            'Route register ist erlaubt' => ["route('register')", false],
        ];
    }

    // AK15
    #[DataProvider('verweisBeispiele')]
    public function test_ak15_suchmuster_erkennt_die_verweise_der_alten_dateien(string $text, bool $treffer): void
    {
        $this->assertSame($treffer, preg_match(self::VERWEIS_MUSTER, $text) === 1);
    }

    // AK15
    public function test_ak15_nichts_im_paket_verweist_auf_die_entfernten_dateien(): void
    {
        $treffer = [];
        foreach (['src', '.project/doku', 'resources-src'] as $ordner) {
            if (! is_dir($this->paketPfad($ordner))) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->paketPfad($ordner), \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $datei) {
                if (! $datei->isFile() || ! preg_match('~\.(php|json|md|js|css|blade\.php|mjs)$~', $datei->getFilename())) {
                    continue;
                }
                if (preg_match(self::VERWEIS_MUSTER, (string) file_get_contents($datei->getPathname())) === 1) {
                    $treffer[] = str_replace($this->paketPfad().'/', '', $datei->getPathname());
                }
            }
        }
        foreach (['README.md', 'composer.json', 'package.json'] as $datei) {
            if (is_file($this->paketPfad($datei)) && preg_match(self::VERWEIS_MUSTER, (string) file_get_contents($this->paketPfad($datei))) === 1) {
                $treffer[] = $datei;
            }
        }

        $this->assertSame([], $treffer, 'Verweise auf entfernte Dateien: '.implode(', ', $treffer));
    }

    // ------------------------------------------------------------------ AK16

    private function readmeAbschnitt(): string
    {
        $readme = (string) file_get_contents($this->paketPfad('README.md'));
        $this->assertNotSame('', $readme);
        $zeilen = preg_split('~\R~', $readme);
        $stufe = null;
        $abschnitt = [];
        $imCodeblock = false;
        foreach ($zeilen as $zeile) {
            if (str_starts_with(ltrim($zeile), '```')) {
                $imCodeblock = ! $imCodeblock;
            }
            if (! $imCodeblock && preg_match('~^(#{1,6})\s+(.*?)\s*$~', $zeile, $t) === 1) {
                if ($stufe !== null && strlen($t[1]) <= $stufe) {
                    break;
                }
                if ($stufe === null && preg_match('~^Registrierung$~u', trim($t[2])) === 1) {
                    $stufe = strlen($t[1]);
                }
            }
            if ($stufe !== null) {
                $abschnitt[] = $zeile;
            }
        }

        return implode("\n", $abschnitt);
    }

    // AK16
    public function test_ak16_readme_hat_einen_abschnitt_registrierung(): void
    {
        $this->assertNotSame('', $this->readmeAbschnitt(), 'Kein Abschnitt mit der Überschrift „Registrierung“ in der README.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function readmeInhalte(): array
    {
        return [
            'Schalter' => ['~USERAUTH_REGISTRATION(?!_ROLE)\b~'],
            'Rolle (env)' => ['~USERAUTH_REGISTRATION_ROLE~'],
            'Gültigkeit des Links' => ['~verify_ttl_minutes~'],
            'APP_URL' => ['~APP_URL~'],
            'Migration' => ['~php artisan migrate~'],
            'privilegierte Rolle dev' => ['~\bdev\b~'],
            'privilegierte Rolle admin' => ['~\badmin\b~'],
        ];
    }

    // AK16
    #[DataProvider('readmeInhalte')]
    public function test_ak16_abschnitt_nennt_die_pflichtangaben(string $inhalt): void
    {
        $abschnitt = $this->readmeAbschnitt();
        $this->assertNotSame('', $abschnitt, 'Gegenprobe: Der Abschnitt „Registrierung“ existiert.');

        $this->assertMatchesRegularExpression($inhalt, $abschnitt);
    }

    // AK16
    public function test_ak16_abschnitt_nennt_mail_versand_doppelte_email_und_upgrade(): void
    {
        $abschnitt = $this->readmeAbschnitt();
        $this->assertNotSame('', $abschnitt, 'Gegenprobe: Der Abschnitt „Registrierung“ existiert.');

        $this->assertMatchesRegularExpression('~mail~i', $abschnitt, 'Pflicht des Hosts: Mail-Versand fehlt.');
        $this->assertMatchesRegularExpression('~doppelt|bereits vergeben|existiert schon|vorhanden~iu', $abschnitt, 'Verhalten bei doppelter E-Mail fehlt.');
        $this->assertMatchesRegularExpression('~upgrade~i', $abschnitt, 'Upgrade-Hinweis fehlt.');
    }
}
