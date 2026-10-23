<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Haertung;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Feature\SocialLogin\SocialLoginTestCase;
use ITHilbert\UserAuth\Tests\Support\NachrichtenAbfangen;

/**
 * Zwei-Faktor-Anmeldung, sitzungsgebunden. Abgedeckt: AK14–AK22 (userauth-haertung, Befunde S5–S8, Q4).
 *
 * Jede "Sitzung" ist ein eigener Browser mit eigener Session-ID (Cookie). Die Hilfen `browser()` und `senden()`
 * halten die Sitzungen getrennt und folgen einer neuen Session-ID nach `regenerate()`/`migrate()`.
 * Der Code wird ausschließlich aus der abgefangenen Mail gelesen.
 */
final class ZweiFaktorTest extends SocialLoginTestCase
{
    use NachrichtenAbfangen;

    private const EMAIL = 'max@firma.de';

    /** @var array<string, string> */
    private array $sitzungen = [];

    private string $aktiv = 'a';

    private Model $nutzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nachrichtenAbfangen();
        View::addLocation(__DIR__.'/../../Fixtures/views');
        config()->set('userauth.two_factor_enabled', true);
        config()->set('userauth.audit_log_enabled', false);
        config()->set('userauth.password_policy.enabled', false);

        $this->nutzer = $this->makeUser(['email' => self::EMAIL]);
        $this->linkGoogle($this->nutzer, 'google-sub-1');
        $this->provider->returnIdentity('google-sub-1', self::EMAIL);

        $this->browser('a');
    }

    // ---------------------------------------------------------------- Hilfen

    private function browser(string $name, ?string $sessionId = null): void
    {
        $this->aktiv = $name;
        $this->sitzungen[$name] = $sessionId ?? $this->sitzungen[$name] ?? Str::random(40);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withCookie((string) config('session.cookie'), $this->sitzungen[$name]);
    }

    /**
     * @param  array<string, mixed>  $daten
     */
    private function senden(string $methode, string $uri, array $daten = []): TestResponse
    {
        // Cookies aus früheren Anfragen dürfen nicht an dieser Antwort hängen bleiben (gemeinsamer Cookie-Jar der Testanwendung)
        $this->app['cookie']->flushQueuedCookies();
        $antwort = $this->{$methode}($uri, $daten);

        $this->sitzungen[$this->aktiv] = session()->getId();
        $this->withCookie((string) config('session.cookie'), $this->sitzungen[$this->aktiv]);

        return $antwort;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function passwortAnmelden(array $extra = []): TestResponse
    {
        return $this->senden('post', route('login'), array_merge(['email' => self::EMAIL, 'password' => 'password'], $extra));
    }

    private function sozialAnmelden(): TestResponse
    {
        return $this->senden('get', route('userauth.social.callback', 'google'));
    }

    private function codeEingeben(string $code): TestResponse
    {
        $this->from(route('verify.index'));

        return $this->senden('post', route('verify.store'), ['two_factor_code' => $code]);
    }

    /**
     * Meldet per Passwort an (2FA offen) und liefert den Code aus der Mail.
     */
    private function offeneZweiFaktor(): string
    {
        $this->passwortAnmelden()->assertRedirect(route('verify.index'));

        return $this->zweiFaktorCode(self::EMAIL);
    }

    private function falscherCode(string $richtiger): string
    {
        return $richtiger === '000000' ? '111111' : '000000';
    }

    /**
     * Cookie-Namen der Antwort.
     *
     * @return list<string>
     */
    private function cookieNamen(TestResponse $antwort): array
    {
        return array_map(fn ($cookie): string => $cookie->getName(), $antwort->headers->getCookies());
    }

    private function hatRememberCookie(TestResponse $antwort): bool
    {
        foreach ($this->cookieNamen($antwort) as $name) {
            if (str_starts_with($name, 'remember_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gegenprobe für "bleibt unangemeldet": Die Sitzung ist geladen und sieht ihre offene 2FA.
     */
    private function assertZweiFaktorOffen(): void
    {
        $this->senden('get', route('verify.index'))->assertOk();
    }

    private function assertNichtImKlartextInDatenbank(string $code): void
    {
        $inhalt = json_encode($this->alleTabellenInhalte(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString(self::EMAIL, $inhalt, 'Gegenprobe: Die Datenbank wurde nicht durchsucht (Nutzerzeile fehlt).');
        $this->assertDoesNotMatchRegularExpression('/(?<!\d)'.$code.'(?!\d)/', $inhalt);
    }

    private function assertNichtImKlartextInSession(string $code): void
    {
        $this->assertNotEmpty(session()->all(), 'Gegenprobe: Die Sitzung ist leer.');
        $this->assertDoesNotMatchRegularExpression('/(?<!\d)'.$code.'(?!\d)/', json_encode(session()->all(), JSON_THROW_ON_ERROR));
    }

    private function assertNichtErreichbar(string $url): void
    {
        $antwort = $this->senden('get', $url);

        $this->assertContains($antwort->getStatusCode(), [301, 302, 303, 307, 401, 403], "Seite {$url} darf vor bestandener 2FA nicht erreichbar sein, Status ".$antwort->getStatusCode());
        $this->assertGuest();
    }

    /**
     * Folgt Weiterleitungen (höchstens 8) und bricht bei einer Schleife ab.
     *
     * @return array{urls: list<string>, antwort: TestResponse}
     */
    private function weiterleitungenFolgen(TestResponse $antwort): array
    {
        $besucht = [];
        for ($hop = 0; $hop < 8 && $antwort->isRedirection(); $hop++) {
            $ziel = (string) $antwort->headers->get('Location');
            $this->assertNotContains($ziel, $besucht, 'Weiterleitungsschleife über '.$ziel);
            $besucht[] = $ziel;
            $antwort = $this->senden('get', $ziel);
        }

        return ['urls' => $besucht, 'antwort' => $antwort];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function alleTabellenInhalte(): array
    {
        $inhalte = [];
        foreach (DB::select("select name from sqlite_master where type = 'table'") as $tabelle) {
            $inhalte[$tabelle->name] = DB::table($tabelle->name)->get()->all();
        }

        return $inhalte;
    }

    private function auditAnzahl(string $event): int
    {
        return DB::table('user_auth_audit_logs')->where('event', $event)->count();
    }

    // ------------------------------------------------------------------ AK14

    // AK14
    public function test_nach_richtigem_passwort_ist_der_nutzer_noch_nicht_angemeldet(): void
    {
        $this->passwortAnmelden()->assertRedirect(route('verify.index'));

        $this->assertGuest();
    }

    // AK14
    public function test_nach_richtigem_passwort_wird_der_code_per_mail_gesendet(): void
    {
        $this->passwortAnmelden();

        $this->assertCount(1, $this->nachrichtenAn(self::EMAIL));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->zweiFaktorCode(self::EMAIL));
    }

    // AK14
    public function test_seiten_hinter_auth_sind_vor_bestandener_2fa_nicht_erreichbar(): void
    {
        $this->passwortAnmelden();
        $this->assertZweiFaktorOffen();

        $this->assertNichtErreichbar(route('password.edit'));
        $this->assertNichtErreichbar(route('usericon.edit'));
    }

    // AK14
    public function test_die_code_eingabe_ist_bei_offener_2fa_erreichbar(): void
    {
        $this->passwortAnmelden();

        $this->senden('get', route('verify.index'))->assertOk();
        $this->assertGuest();
    }

    // AK14 (Nur Code-Eingabe: ohne offene Anmeldung nicht verwendbar)
    public function test_code_eingabe_ohne_offene_anmeldung_meldet_niemanden_an(): void
    {
        $antwort = $this->codeEingeben('123456');

        $this->assertLessThan(500, $antwort->getStatusCode(), 'Gegenprobe: Die Anfrage wurde regulär verarbeitet.');
        $this->assertSame([], $this->gesendeteNachrichten());
        $this->assertGuest();
    }

    // AK14
    public function test_code_eingabe_ohne_offene_anmeldung_leitet_zur_anmeldung(): void
    {
        $this->senden('get', route('verify.index'))->assertRedirect(route('login'));
    }

    // AK14 (Code erneut senden ohne offene Anmeldung versendet nichts)
    public function test_code_erneut_senden_ohne_offene_anmeldung_versendet_nichts(): void
    {
        $antwort = $this->senden('post', route('verify.resend'));

        $this->assertLessThan(500, $antwort->getStatusCode(), 'Gegenprobe: Die Anfrage wurde regulär verarbeitet.');
        $this->assertSame([], $this->gesendeteNachrichten());

        // Gegenprobe: dieselbe Aktion versendet mit offener Anmeldung
        $this->offeneZweiFaktor();
        $this->travel(61)->seconds();
        $this->senden('post', route('verify.resend'));
        $this->assertCount(2, $this->nachrichtenAn(self::EMAIL));
    }

    // AK14
    public function test_remember_cookie_wird_vor_der_2fa_nicht_gesetzt(): void
    {
        $antwort = $this->passwortAnmelden(['remember' => 'on']);

        $this->assertFalse($this->hatRememberCookie($antwort), 'Remember-Cookie darf erst nach bestandener 2FA gesetzt werden: '.implode(',', $this->cookieNamen($antwort)));

        // Gegenprobe: "Angemeldet bleiben" wurde angenommen und wirkt erst nach der 2FA
        $this->assertTrue($this->hatRememberCookie($this->codeEingeben($this->zweiFaktorCode(self::EMAIL))));
    }

    // AK14
    public function test_remember_cookie_wird_nach_bestandener_2fa_gesetzt(): void
    {
        $this->passwortAnmelden(['remember' => 'on']);
        $code = $this->zweiFaktorCode(self::EMAIL);

        $antwort = $this->codeEingeben($code);

        $this->assertAuthenticatedAs($this->nutzer);
        $this->assertTrue($this->hatRememberCookie($antwort), 'Remember-Cookie fehlt nach bestandener 2FA.');
    }

    // AK14 (ohne "Angemeldet bleiben" kein Cookie, auch nach 2FA)
    public function test_ohne_angemeldet_bleiben_wird_auch_nach_der_2fa_kein_remember_cookie_gesetzt(): void
    {
        $code = $this->offeneZweiFaktor();

        $antwort = $this->codeEingeben($code);

        $this->assertAuthenticatedAs($this->nutzer);
        $this->assertFalse($this->hatRememberCookie($antwort));
    }

    // AK14 (Social Login)
    public function test_nach_social_login_ist_der_nutzer_noch_nicht_angemeldet(): void
    {
        $this->sozialAnmelden()->assertRedirect(route('verify.index'));

        $this->assertGuest();
    }

    // AK14 (Social Login)
    public function test_nach_social_login_wird_der_code_per_mail_gesendet(): void
    {
        $this->sozialAnmelden();

        $this->assertCount(1, $this->nachrichtenAn(self::EMAIL));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->zweiFaktorCode(self::EMAIL));
    }

    // AK14 (Social Login)
    public function test_seiten_hinter_auth_sind_nach_social_login_vor_der_2fa_nicht_erreichbar(): void
    {
        $this->sozialAnmelden();
        $this->assertZweiFaktorOffen();

        $this->assertNichtErreichbar(route('password.edit'));
        $this->assertNichtErreichbar(route('usericon.edit'));
    }

    // AK14 (Social Login) + AK15
    public function test_nach_social_login_meldet_der_richtige_code_an(): void
    {
        $this->sozialAnmelden();

        $this->codeEingeben($this->zweiFaktorCode(self::EMAIL));

        $this->assertAuthenticatedAs($this->nutzer);
    }

    // ------------------------------------------------------------------ AK15

    // AK15
    public function test_richtiger_code_meldet_den_nutzer_an(): void
    {
        $code = $this->offeneZweiFaktor();

        $this->codeEingeben($code)->assertRedirect();

        $this->assertAuthenticatedAs($this->nutzer);
    }

    // AK15
    public function test_nach_bestandener_2fa_sind_seiten_hinter_auth_erreichbar(): void
    {
        $code = $this->offeneZweiFaktor();
        $this->codeEingeben($code);

        $this->senden('get', route('password.edit'))->assertOk();
    }

    // AK15
    public function test_nach_bestandener_2fa_wird_zum_urspruenglichen_ziel_geleitet(): void
    {
        $this->senden('get', route('password.edit'))->assertRedirect(route('login'));
        $code = $this->offeneZweiFaktor();

        $this->codeEingeben($code)->assertRedirect(route('password.edit'));
    }

    // AK15
    public function test_nach_bestandener_2fa_hat_die_sitzung_eine_neue_id(): void
    {
        $code = $this->offeneZweiFaktor();
        $vorher = session()->getId();

        $this->codeEingeben($code);

        $this->assertAuthenticatedAs($this->nutzer);
        $this->assertNotSame($vorher, session()->getId());
    }

    // AK15
    public function test_ein_code_innerhalb_von_10_minuten_wird_akzeptiert(): void
    {
        $code = $this->offeneZweiFaktor();
        $this->travel(9)->minutes();

        $this->codeEingeben($code);

        $this->assertAuthenticatedAs($this->nutzer);
    }

    // AK15 ("der Code ist verbraucht": die vor der 2FA gültige Sitzung kann ihn nicht erneut einlösen)
    public function test_der_code_ist_nach_bestandener_2fa_verbraucht(): void
    {
        $code = $this->offeneZweiFaktor();
        $alteSitzung = session()->getId();
        $this->codeEingeben($code);
        $this->assertAuthenticatedAs($this->nutzer);

        $this->browser('wiederholung', $alteSitzung);
        $this->senden('get', route('verify.index'))->assertRedirect(route('login'));
        $this->codeEingeben($code);

        $this->assertGuest();
    }

    // AK15 (der verbrauchte Code gilt auch in einer anderen Sitzung mit offener 2FA nicht)
    public function test_der_verbrauchte_code_gilt_in_einer_anderen_sitzung_mit_offener_2fa_nicht(): void
    {
        $verbraucht = $this->offeneZweiFaktor();
        $this->codeEingeben($verbraucht);
        $this->assertAuthenticatedAs($this->nutzer);

        $this->browser('b');
        $codeB = $this->offeneZweiFaktor();
        if ($verbraucht === $codeB) {
            $this->markTestSkipped('Zufällig gleicher Code.');
        }
        $this->assertZweiFaktorOffen();
        $this->codeEingeben($verbraucht);

        $this->assertGuest();
        $this->assertZweiFaktorOffen();
    }

    // ------------------------------------------------------------------ AK16

    // AK16
    public function test_ein_falscher_code_wird_abgelehnt(): void
    {
        $code = $this->offeneZweiFaktor();

        $this->codeEingeben($this->falscherCode($code))->assertRedirect()->assertSessionHasErrors();

        $this->assertGuest();
        $this->codeEingeben($code);
        $this->assertAuthenticatedAs($this->nutzer);
    }

    // AK16 (Hinweis bis in die Seite)
    public function test_der_hinweis_bei_falschem_code_steht_in_der_code_seite(): void
    {
        $code = $this->offeneZweiFaktor();

        $antwort = $this->codeEingeben($this->falscherCode($code));
        $fehler = session('errors');
        $this->assertNotNull($fehler);
        $this->assertNotEmpty($fehler->all());

        $this->senden('get', (string) $antwort->headers->get('Location'))->assertOk()->assertSee($fehler->all()[0]);
    }

    // AK16
    public function test_ein_abgelaufener_code_wird_abgelehnt(): void
    {
        $code = $this->offeneZweiFaktor();
        $this->travel(11)->minutes();

        $this->assertZweiFaktorOffen();
        $this->codeEingeben($code)->assertSessionHasErrors();

        $this->assertGuest();

        // Gegenprobe: die Sitzung ist geladen, ein neuer Code meldet an
        $this->senden('post', route('verify.resend'));
        $this->codeEingeben($this->zweiFaktorCode(self::EMAIL));
        $this->assertAuthenticatedAs($this->nutzer);
    }

    // AK16 (Hinweis bis in die Seite)
    public function test_der_hinweis_bei_abgelaufenem_code_steht_in_der_zielseite(): void
    {
        $code = $this->offeneZweiFaktor();
        $this->travel(11)->minutes();

        $antwort = $this->codeEingeben($code);
        $fehler = session('errors');
        $this->assertNotNull($fehler);
        $this->assertNotEmpty($fehler->all());

        $this->senden('get', (string) $antwort->headers->get('Location'))->assertOk()->assertSee($fehler->all()[0]);
    }

    // AK16
    public function test_nach_4_fehlversuchen_gilt_der_richtige_code_noch(): void
    {
        $code = $this->offeneZweiFaktor();
        for ($i = 0; $i < 4; $i++) {
            $this->codeEingeben($this->falscherCode($code));
        }

        $this->codeEingeben($code);

        $this->assertAuthenticatedAs($this->nutzer);
    }

    // AK16
    public function test_nach_5_fehlversuchen_ist_der_code_ungueltig(): void
    {
        $code = $this->offeneZweiFaktor();
        for ($i = 0; $i < 4; $i++) {
            $this->codeEingeben($this->falscherCode($code));
        }
        $this->assertZweiFaktorOffen();
        $this->codeEingeben($this->falscherCode($code));

        $this->codeEingeben($code);

        $this->assertGuest();
    }

    // AK16
    public function test_nach_5_fehlversuchen_landet_der_nutzer_wieder_auf_der_anmeldung(): void
    {
        $code = $this->offeneZweiFaktor();
        for ($i = 0; $i < 4; $i++) {
            $this->codeEingeben($this->falscherCode($code))->assertRedirect(route('verify.index'));
        }

        $this->codeEingeben($this->falscherCode($code))->assertRedirect(route('login'));
        $this->senden('get', route('login'))->assertOk();
    }

    // AK16
    public function test_nach_5_fehlversuchen_fuehrt_die_code_seite_zur_anmeldung(): void
    {
        $code = $this->offeneZweiFaktor();
        for ($i = 0; $i < 5; $i++) {
            $this->codeEingeben($this->falscherCode($code));
        }

        $this->senden('get', route('verify.index'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------ AK17

    // AK17
    public function test_die_bestaetigung_in_einer_sitzung_laesst_die_andere_unangemeldet(): void
    {
        $this->browser('a');
        $codeA = $this->offeneZweiFaktor();
        $this->browser('b');
        $this->offeneZweiFaktor();

        $this->browser('a');
        $this->codeEingeben($codeA);
        $this->assertAuthenticatedAs($this->nutzer);

        $this->browser('b');
        $this->assertZweiFaktorOffen();
        $this->assertNichtErreichbar(route('password.edit'));
    }

    // AK17
    public function test_ein_code_gilt_nur_in_der_sitzung_fuer_die_er_erzeugt_wurde(): void
    {
        $this->browser('a');
        $codeA = $this->offeneZweiFaktor();
        $this->browser('b');
        $codeB = $this->offeneZweiFaktor();
        if ($codeA === $codeB) {
            $this->markTestSkipped('Zufällig gleicher Code in beiden Sitzungen.');
        }

        $this->assertZweiFaktorOffen();
        $this->codeEingeben($codeA);

        $this->assertGuest();
        $this->assertZweiFaktorOffen();
    }

    // AK17
    public function test_jede_sitzung_kann_ihren_eigenen_code_bestaetigen(): void
    {
        $this->browser('a');
        $this->offeneZweiFaktor();
        $this->browser('b');
        $codeB = $this->offeneZweiFaktor();

        $this->codeEingeben($codeB);

        $this->assertAuthenticatedAs($this->nutzer);
    }

    // AK17
    public function test_die_bestaetigung_der_zweiten_sitzung_meldet_die_erste_nicht_an(): void
    {
        $this->browser('a');
        $this->offeneZweiFaktor();
        $this->browser('b');
        $codeB = $this->offeneZweiFaktor();
        $this->codeEingeben($codeB);

        $this->browser('a');
        $this->assertZweiFaktorOffen();

        $this->assertNichtErreichbar(route('password.edit'));
    }

    // ------------------------------------------------------------------ AK18

    // AK18
    public function test_der_code_besteht_aus_6_ziffern(): void
    {
        $code = $this->offeneZweiFaktor();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    // AK18
    public function test_der_code_steht_nicht_im_klartext_in_der_datenbank(): void
    {
        $code = $this->offeneZweiFaktor();

        $this->assertNichtImKlartextInDatenbank($code);
    }

    // AK18
    public function test_der_code_steht_nicht_im_klartext_in_der_session(): void
    {
        $code = $this->offeneZweiFaktor();

        $this->assertNichtImKlartextInSession($code);
    }

    // AK18 (nach "Code erneut senden" gilt dasselbe für den neuen Code)
    public function test_der_neue_code_nach_erneutem_senden_steht_nicht_im_klartext_in_db_und_session(): void
    {
        $this->offeneZweiFaktor();
        $this->travel(61)->seconds();
        $this->senden('post', route('verify.resend'));
        $neu = $this->zweiFaktorCode(self::EMAIL);

        $this->assertNichtImKlartextInDatenbank($neu);
        $this->assertNichtImKlartextInSession($neu);
    }

    // AK18 (nach bestandener 2FA bleibt kein Klartext zurück)
    public function test_nach_bestandener_2fa_steht_der_code_nicht_im_klartext_in_db_und_session(): void
    {
        $code = $this->offeneZweiFaktor();
        $this->codeEingeben($code);

        $this->assertAuthenticatedAs($this->nutzer);
        $this->assertNichtImKlartextInDatenbank($code);
        $this->assertNichtImKlartextInSession($code);
    }

    // ------------------------------------------------------------------ AK19

    // AK19
    public function test_code_erneut_senden_ist_per_get_nicht_moeglich(): void
    {
        $this->offeneZweiFaktor();
        $this->travel(61)->seconds();
        $vorher = count($this->gesendeteNachrichten());

        $this->senden('get', route('verify.resend'))->assertStatus(405);

        $this->assertCount($vorher, $this->gesendeteNachrichten());

        // Gegenprobe: dieselbe Sitzung darf per POST einen Code anfordern
        $this->senden('post', route('verify.resend'));
        $this->assertCount($vorher + 1, $this->gesendeteNachrichten());
    }

    // AK19
    public function test_code_erneut_senden_per_post_versendet_einen_neuen_code(): void
    {
        $this->offeneZweiFaktor();
        $this->travel(61)->seconds();

        $this->senden('post', route('verify.resend'))->assertRedirect();

        $this->assertCount(2, $this->nachrichtenAn(self::EMAIL));
    }

    // AK19
    public function test_code_erneut_senden_ist_auf_einmal_pro_60_sekunden_begrenzt(): void
    {
        $this->offeneZweiFaktor();
        $this->travel(61)->seconds();
        $this->senden('post', route('verify.resend'));
        $this->assertCount(2, $this->nachrichtenAn(self::EMAIL), 'Gegenprobe: Der erste erneute Versand geht raus.');

        $zweite = $this->senden('post', route('verify.resend'));

        $this->assertLessThan(500, $zweite->getStatusCode());
        $this->assertCount(2, $this->nachrichtenAn(self::EMAIL), 'Innerhalb von 60 Sekunden darf nur ein erneuter Versand erfolgen.');
    }

    // AK19
    public function test_nach_60_sekunden_ist_erneutes_senden_wieder_moeglich(): void
    {
        $this->offeneZweiFaktor();
        $this->travel(61)->seconds();
        $this->senden('post', route('verify.resend'));
        $this->travel(61)->seconds();

        $this->senden('post', route('verify.resend'));

        $this->assertCount(3, $this->nachrichtenAn(self::EMAIL));
    }

    // AK19
    public function test_der_neue_code_ersetzt_den_alten(): void
    {
        $alt = $this->offeneZweiFaktor();
        $this->travel(61)->seconds();
        $this->senden('post', route('verify.resend'));
        $neu = $this->zweiFaktorCode(self::EMAIL);
        if ($alt === $neu) {
            $this->markTestSkipped('Zufällig gleicher Code.');
        }

        $this->codeEingeben($alt);

        $this->assertGuest();
        $this->codeEingeben($neu);
        $this->assertAuthenticatedAs($this->nutzer);
    }

    // AK19
    public function test_der_neue_code_nach_erneutem_senden_meldet_an(): void
    {
        $this->offeneZweiFaktor();
        $this->travel(61)->seconds();
        $this->senden('post', route('verify.resend'));
        $neu = $this->zweiFaktorCode(self::EMAIL);

        $this->codeEingeben($neu);

        $this->assertAuthenticatedAs($this->nutzer);
    }

    // ------------------------------------------------------------------ AK20

    // AK20
    public function test_abmelden_bei_offener_2fa_leitet_weiter_ohne_fehler(): void
    {
        $this->offeneZweiFaktor();
        $this->assertZweiFaktorOffen();

        $antwort = $this->senden('post', route('logout'));

        $this->assertLessThan(400, $antwort->getStatusCode());
        $this->assertGuest();
    }

    // AK20
    public function test_nach_dem_abmelden_ist_die_code_seite_nicht_mehr_offen(): void
    {
        $this->offeneZweiFaktor();
        $this->assertZweiFaktorOffen();
        $this->senden('post', route('logout'));

        $this->senden('get', route('verify.index'))->assertRedirect(route('login'));
    }

    // AK20
    public function test_nach_dem_abmelden_meldet_der_alte_code_nicht_mehr_an(): void
    {
        $code = $this->offeneZweiFaktor();
        $this->assertZweiFaktorOffen();
        $this->senden('post', route('logout'));

        $this->codeEingeben($code);

        $this->assertGuest();
    }

    // AK20 (die vor dem Abmelden gültige Sitzung ist verworfen)
    public function test_die_alte_sitzung_kann_nach_dem_abmelden_den_code_nicht_mehr_einloesen(): void
    {
        $code = $this->offeneZweiFaktor();
        $alteSitzung = session()->getId();
        $this->browser('alt', $alteSitzung);
        $this->assertZweiFaktorOffen();
        $this->senden('post', route('logout'));

        $this->browser('alt', $alteSitzung);
        $this->senden('get', route('verify.index'))->assertRedirect(route('login'));
        $this->codeEingeben($code);

        $this->assertGuest();
    }

    // AK20
    public function test_erst_die_2fa_dann_die_passwortaenderung_ohne_schleife(): void
    {
        config()->set('userauth.password_policy.enabled', true);
        config()->set('userauth.password_policy.require_change_every_days', 90);
        config()->set('userauth.redirect_after_login', '/usericon/edit');
        DB::table('users')->where('id', $this->nutzer->getKey())->update(['password_changed_at' => now()->subDays(100)]);

        $this->passwortAnmelden()->assertRedirect(route('verify.index'));
        $this->senden('get', route('verify.index'))->assertOk();
        $code = $this->zweiFaktorCode(self::EMAIL);

        $ergebnis = $this->weiterleitungenFolgen($this->codeEingeben($code));

        $this->assertAuthenticatedAs($this->nutzer);
        $this->assertSame(route('password.edit'), end($ergebnis['urls']), 'Nach der 2FA muss die Passwortänderung folgen.');
        $ergebnis['antwort']->assertOk();
    }

    // AK20
    public function test_abgelaufenes_passwort_bei_social_login_erst_2fa_dann_passwortaenderung(): void
    {
        config()->set('userauth.password_policy.enabled', true);
        config()->set('userauth.password_policy.require_change_every_days', 90);
        config()->set('userauth.redirect_after_login', '/usericon/edit');
        DB::table('users')->where('id', $this->nutzer->getKey())->update(['password_changed_at' => now()->subDays(100)]);

        $this->sozialAnmelden()->assertRedirect(route('verify.index'));
        $code = $this->zweiFaktorCode(self::EMAIL);

        $ergebnis = $this->weiterleitungenFolgen($this->codeEingeben($code));

        $this->assertSame(route('password.edit'), end($ergebnis['urls']));
        $ergebnis['antwort']->assertOk();
    }

    // ------------------------------------------------------------------ AK21

    // AK21
    public function test_ohne_2fa_ist_der_nutzer_nach_social_login_sofort_angemeldet(): void
    {
        config()->set('userauth.two_factor_enabled', false);

        $this->sozialAnmelden()->assertRedirect('/');

        $this->assertAuthenticatedAs($this->nutzer);
        $this->assertSame([], $this->gesendeteNachrichten());
    }

    // AK21
    public function test_ohne_2fa_ist_der_nutzer_nach_passwort_sofort_angemeldet_und_erreicht_geschuetzte_seiten(): void
    {
        config()->set('userauth.two_factor_enabled', false);

        $this->passwortAnmelden();

        $this->assertAuthenticatedAs($this->nutzer);
        $this->senden('get', route('password.edit'))->assertOk();
    }

    // AK21
    public function test_ohne_2fa_wird_das_remember_cookie_sofort_gesetzt(): void
    {
        config()->set('userauth.two_factor_enabled', false);

        $antwort = $this->passwortAnmelden(['remember' => 'on']);

        $this->assertTrue($this->hatRememberCookie($antwort));
    }

    // ------------------------------------------------------------------ AK22

    // AK22
    public function test_login_wird_erst_nach_bestandener_2fa_protokolliert(): void
    {
        config()->set('userauth.audit_log_enabled', true);

        $code = $this->offeneZweiFaktor();
        $this->assertSame(0, $this->auditAnzahl('login'), 'login darf vor der 2FA nicht protokolliert werden.');

        $this->codeEingeben($code);

        $this->assertSame(1, $this->auditAnzahl('login'));
        $this->assertDatabaseHas('user_auth_audit_logs', ['event' => 'login', 'user_id' => $this->nutzer->getKey(), 'email' => self::EMAIL]);
    }

    // AK22 (Social Login)
    public function test_login_wird_nach_social_login_erst_nach_bestandener_2fa_protokolliert(): void
    {
        config()->set('userauth.audit_log_enabled', true);

        $this->sozialAnmelden();
        $this->assertSame(0, $this->auditAnzahl('login'));

        $this->codeEingeben($this->zweiFaktorCode(self::EMAIL));

        $this->assertSame(1, $this->auditAnzahl('login'));
    }

    // AK22
    public function test_ein_abgelehnter_code_wird_als_eigenes_ereignis_protokolliert(): void
    {
        config()->set('userauth.audit_log_enabled', true);
        $code = $this->offeneZweiFaktor();

        $this->codeEingeben($this->falscherCode($code));

        $eigene = DB::table('user_auth_audit_logs')
            ->whereNotIn('event', ['login', 'logout', 'failed'])
            ->where(fn ($q) => $q->where('user_id', $this->nutzer->getKey())->orWhere('email', self::EMAIL))
            ->count();
        $this->assertSame(1, $eigene, 'Ein abgelehnter Code muss als eigenes Ereignis (nicht login/logout/failed) protokolliert werden.');
    }

    // AK22
    public function test_ein_abgelehnter_code_wird_nicht_als_login_protokolliert(): void
    {
        config()->set('userauth.audit_log_enabled', true);
        $code = $this->offeneZweiFaktor();

        $this->codeEingeben($this->falscherCode($code));

        $this->assertGreaterThan(0, DB::table('user_auth_audit_logs')->count(), 'Gegenprobe: Das Audit-Log ist aktiv und hat den Versuch erfasst.');
        $this->assertSame(0, $this->auditAnzahl('login'));
    }

    // AK22 (Gegenprobe: ohne aktives Audit-Log wird nichts protokolliert)
    public function test_ohne_aktives_audit_log_wird_nichts_protokolliert(): void
    {
        $code = $this->offeneZweiFaktor();
        $this->codeEingeben($this->falscherCode($code));
        $this->codeEingeben($code);

        $this->assertAuthenticatedAs($this->nutzer);
        $this->assertSame(0, DB::table('user_auth_audit_logs')->count());
    }

    // ------------------------------------------------------- Randfall: Altsitzungen

    /**
     * Sitzung aus der Zeit vor dem Update: Der Nutzer gilt über den Guard-Schlüssel bereits als angemeldet
     * (der alte Ablauf meldete nach dem Passwort an), "2FA offen" stand nur in `users.two_factor_code`.
     */
    private function altsitzungAnlegen(string $name): string
    {
        $sitzung = $this->app['session']->driver();
        $sitzung->setId($id = Str::random(40));
        $sitzung->put($this->app['auth']->guard()->getName(), $this->nutzer->getKey());
        $sitzung->save();

        $this->browser($name, $id);

        return $id;
    }

    private function alterCodeInDatenbank(?string $code): void
    {
        DB::table('users')->where('id', $this->nutzer->getKey())->update([
            'two_factor_code' => $code,
            'two_factor_expires_at' => $code === null ? null : now()->addMinutes(5),
        ]);
    }

    // Randfall (offene 2FA-Codes aus der Zeit vor dem Update sind danach ungültig; Betroffene melden sich neu an)
    public function test_eine_altsitzung_mit_altem_offenem_code_erreicht_keine_geschuetzte_seite_mehr(): void
    {
        $this->alterCodeInDatenbank(null);
        $this->altsitzungAnlegen('alt');
        $this->senden('get', route('password.edit'))->assertOk();
        $this->assertAuthenticatedAs($this->nutzer);

        $this->alterCodeInDatenbank('123456');
        $this->app['auth']->forgetGuards();
        $this->senden('get', route('password.edit'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    // Randfall (Betroffene melden sich neu an)
    public function test_nach_dem_abmelden_der_altsitzung_bleibt_der_neue_login_nur_per_2fa_moeglich(): void
    {
        $alteSitzung = $this->altsitzungAnlegen('alt');
        $this->alterCodeInDatenbank('123456');
        $this->senden('get', route('password.edit'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->browser('alt', $alteSitzung);
        $this->senden('get', route('password.edit'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->browser('neu');
        $this->passwortAnmelden()->assertRedirect(route('verify.index'));
        $this->assertGuest();
        $this->codeEingeben($this->zweiFaktorCode(self::EMAIL));
        $this->assertAuthenticatedAs($this->nutzer);
        $this->senden('get', route('password.edit'))->assertOk();
    }

    // Randfall (Gegenprobe: ein Nutzer ohne alten Code bleibt mit seiner bestehenden Sitzung angemeldet)
    public function test_eine_bestehende_sitzung_ohne_alten_code_bleibt_angemeldet(): void
    {
        $this->alterCodeInDatenbank(null);
        $this->altsitzungAnlegen('alt');

        $this->senden('get', route('password.edit'))->assertOk();
        $this->senden('get', route('usericon.edit'))->assertOk();

        $this->assertAuthenticatedAs($this->nutzer);
    }

    // Randfall (der alte Code aus der Datenbank löst nichts ein, auch nicht in einer neuen Anmeldung)
    public function test_der_alte_code_aus_der_datenbank_loest_nichts_ein(): void
    {
        $this->alterCodeInDatenbank('123456');

        $this->passwortAnmelden()->assertRedirect(route('verify.index'));
        $neu = $this->zweiFaktorCode(self::EMAIL);
        $this->assertZweiFaktorOffen();
        if ($neu === '123456') {
            $this->markTestSkipped('Zufällig gleicher Code.');
        }

        $this->codeEingeben('123456');
        $this->assertGuest();

        $this->codeEingeben($neu);
        $this->assertAuthenticatedAs($this->nutzer);
    }

    // Randfall (Gegenprobe: ein neu angemeldeter Nutzer bleibt trotz altem Code in der Datenbank angemeldet)
    public function test_ein_nach_dem_update_angemeldeter_nutzer_bleibt_trotz_altem_code_in_der_datenbank_angemeldet(): void
    {
        $code = $this->offeneZweiFaktor();
        $this->codeEingeben($code);
        $this->assertAuthenticatedAs($this->nutzer);

        $this->alterCodeInDatenbank('123456');

        $this->senden('get', route('password.edit'))->assertOk();
        $this->assertAuthenticatedAs($this->nutzer);
    }
}
