<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Registrierung;

use Carbon\Carbon;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Mail\SocialLinkConfirmation;
use ITHilbert\UserAuth\Tests\Fakes\FakeSocialIdentityProvider;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\AntwortMarke;
use ITHilbert\UserAuth\Tests\Support\RegistrierungHilfen;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Bestätigung der E-Mail nach der Registrierung (userauth-registrierung, Dashboard #255):
 * Mail und Link, Login-Sperre, Bestätigen, ungültige Links, Link erneut anfordern, Audit `email_verified`, Randfälle 2–6.
 */
final class BestaetigungTest extends TestCase
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

    private function bestaetigt(string $email): bool
    {
        return $this->konto($email)->email_verified_at !== null;
    }

    /**
     * Signierte Links, die nicht (mehr) bestätigen: kein Konto bestätigt, Hinweis in der Seite, kein 500.
     */
    private function assertLinkUngueltig(string $link, string $email): void
    {
        $vorher = DB::table('users')->get()->all();

        $antwort = $this->get($link);

        $this->assertLessThan(500, $antwort->getStatusCode(), 'Ungültiger Link darf keinen Serverfehler ergeben.');
        $this->assertEquals($vorher, DB::table('users')->get()->all(), 'Ein ungültiger Link hat ein Konto verändert.');
        $seite = $antwort->isRedirect() ? $this->get($antwort->headers->get('Location')) : $antwort;
        $seite->assertOk();
        $html = (string) $seite->getContent();
        $this->assertStringContainsString('Link ungültig oder abgelaufen', $this->seitentext($html));
        $this->assertTrue($this->hatWegZumErneutenAnfordern($html), 'Die Seite zeigt keinen Weg, den Link erneut anzufordern.');
    }

    // ------------------------------------------------------------------- AK6

    // AK6
    public function test_ak6_nach_der_registrierung_geht_genau_eine_mail_an_die_email_des_kontos(): void
    {
        $this->registrieren();

        $nachrichten = $this->gesendeteNachrichten();
        $this->assertCount(1, $nachrichten);
        $this->assertSame([self::EMAIL], $nachrichten[0]['an']);
        $this->assertNotSame('', $this->linkAusMail($nachrichten[0]));
    }

    // AK6
    public function test_ak6_link_ist_signiert_und_oeffnet_die_bestaetigung(): void
    {
        $link = $this->registriertMitLink();

        $this->assertTrue(URL::hasValidSignature(Request::create($link)), 'Der Link hat keine gültige Signatur.');
        $this->assertFalse(URL::hasValidSignature(Request::create($this->linkVeraendern($link, 'signatur_veraendert'))));
        $this->assertNotSame(404, $this->get($link)->getStatusCode());
        $this->assertTrue($this->bestaetigt(self::EMAIL));
    }

    // AK6
    public function test_ak6_link_gilt_standardmaessig_60_minuten(): void
    {
        $a = $this->registriertMitLink('a@example.test');
        $b = $this->registriertMitLink('b@example.test');

        $this->travel(59)->minutes();
        $this->get($a);
        $this->assertTrue($this->bestaetigt('a@example.test'), 'Nach 59 Minuten muss der Link noch gelten.');

        $this->travel(2)->minutes();
        $this->assertLinkUngueltig($b, 'b@example.test');
        $this->assertFalse($this->bestaetigt('b@example.test'));
    }

    /**
     * @return array<string, array{mixed, int}> ttl-Konfiguration, tatsächliche Minuten
     */
    public static function laufzeiten(): array
    {
        return [
            'Eintrag fehlt (Standard 60)' => [self::NICHT_GESETZT, 60],
            '15 Minuten' => [15, 15],
            '120 Minuten' => [120, 120],
        ];
    }

    // AK6
    #[DataProvider('laufzeiten')]
    public function test_ak6_laufzeit_folgt_der_konfiguration(mixed $ttl, int $minuten): void
    {
        $this->neuStarten(['ttl' => $ttl]);
        $a = $this->registriertMitLink('a@example.test');
        $b = $this->registriertMitLink('b@example.test');

        $this->travel($minuten - 1)->minutes();
        $this->get($a);
        $this->assertTrue($this->bestaetigt('a@example.test'));

        $this->travel(2)->minutes();
        $this->get($b);
        $this->assertFalse($this->bestaetigt('b@example.test'));
    }

    // AK6
    public function test_ak6_leere_laufzeit_ergibt_keinen_sofort_abgelaufenen_link(): void
    {
        $this->neuStarten(['ttl' => '']);
        $link = $this->registriertMitLink();

        $antwort = $this->get($link);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $this->assertTrue($this->bestaetigt(self::EMAIL));
    }

    // AK6
    public function test_ak6_link_ist_an_konto_und_email_gebunden(): void
    {
        $linkA = $this->registriertMitLink('a@example.test');
        $this->registriertMitLink('b@example.test');

        $fremd = $this->linkVeraendern($linkA, 'konto_id_ausgetauscht');
        $this->assertLinkUngueltig($fremd, 'b@example.test');

        $this->assertFalse($this->bestaetigt('b@example.test'));
        $this->assertFalse($this->bestaetigt('a@example.test'));
        $this->get($linkA);
        $this->assertTrue($this->bestaetigt('a@example.test'), 'Gegenprobe: Der eigene Link bestätigt das Konto.');
        $this->assertFalse($this->bestaetigt('b@example.test'));
    }

    // AK6
    public function test_ak6_link_wird_aus_app_url_gebildet_nicht_aus_dem_host_der_anfrage(): void
    {
        config()->set('app.url', 'https://konto.example.test');

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.150'])->from('/register')
            ->post('http://angreifer.example/register', $this->gueltigeDaten());

        $link = $this->bestaetigungsLink(self::EMAIL);
        $this->assertStringStartsWith('https://konto.example.test/', $link);
        $this->assertStringNotContainsString('angreifer.example', $link);
        $this->get($link);
        $this->assertTrue($this->bestaetigt(self::EMAIL), 'Der Link aus APP_URL bestätigt das Konto.');
    }

    // AK6
    public function test_ak6_link_folgt_einer_geaenderten_app_url(): void
    {
        config()->set('app.url', 'https://erste.example.test');
        $this->registrieren(['email' => 'a@example.test']);
        config()->set('app.url', 'https://zweite.example.test');
        $this->registrieren(['email' => 'b@example.test']);

        $this->assertStringStartsWith('https://erste.example.test/', $this->bestaetigungsLink('a@example.test'));
        $this->assertStringStartsWith('https://zweite.example.test/', $this->bestaetigungsLink('b@example.test'));
    }

    // AK6
    public function test_ak6_mail_wird_erst_nach_der_antwort_versendet(): void
    {
        $this->neuStarten([], false);
        $gesendet = 0;
        Event::listen(MessageSent::class, static function () use (&$gesendet): void {
            $gesendet++;
        });
        $beiAntwort = null;
        AntwortMarke::$beiAntwort = static function () use (&$gesendet, &$beiAntwort): void {
            $beiAntwort ??= $gesendet;
        };
        app(Kernel::class)->appendMiddlewareToGroup('web', AntwortMarke::class);

        $this->registrieren();

        $this->assertSame(1, $gesendet, 'Gegenprobe: Die Mail wurde versendet.');
        $this->assertSame(0, $beiAntwort, 'Die Mail wurde schon vor der Antwort versendet (erwartet: defer() nach der Antwort).');
        AntwortMarke::$beiAntwort = null;
    }

    // ------------------------------------------------------------------- AK7

    // AK7
    public function test_ak7_vor_der_bestaetigung_wird_nicht_angemeldet(): void
    {
        $this->registrieren();
        $this->assertCount(1, $this->konten(self::EMAIL), 'Gegenprobe: Das Konto existiert.');

        $this->anmelden(self::EMAIL)->assertRedirect();

        $this->assertGuest();
    }

    // AK7
    public function test_ak7_hinweis_auf_die_offene_bestaetigung_und_weg_zum_erneuten_anfordern(): void
    {
        $this->registrieren();
        $this->anmelden('unbekannt@example.test', 'falsch');
        $falsch = $this->seitentext((string) $this->get('/login')->getContent());

        $this->anmelden(self::EMAIL)->assertRedirect();
        $seite = $this->get('/login');

        $html = (string) $seite->getContent();
        $text = $this->seitentext($html);
        $this->assertNotSame($falsch, $text, 'Der Hinweis unterscheidet sich nicht von einer falschen Anmeldung.');
        $this->assertStringContainsString('bestätig', mb_strtolower($text));
        $this->assertTrue($this->hatWegZumErneutenAnfordern($html), 'Kein Weg zum erneuten Anfordern.');
    }

    // AK7
    public function test_ak7_hinweis_steht_auch_in_der_seite_nach_dem_gefolgten_login(): void
    {
        $this->registrieren();

        $seite = $this->from('/login')->followingRedirects()->post('/login', ['email' => self::EMAIL, 'password' => self::PASSWORT]);

        $seite->assertOk();
        $this->assertStringContainsString('bestätig', mb_strtolower($this->seitentext((string) $seite->getContent())));
        $this->assertTrue($this->hatWegZumErneutenAnfordern((string) $seite->getContent()));
    }

    // AK7
    public function test_ak7_geschuetzte_seiten_bleiben_unerreichbar_bis_zur_bestaetigung(): void
    {
        $link = $this->registriertMitLink();
        $this->anmelden(self::EMAIL);

        $this->get('/password/edit')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get($link);
        $this->anmelden(self::EMAIL);
        $this->get('/password/edit')->assertOk();
    }

    // AK7
    public function test_ak7_mit_zwei_faktor_wird_vor_der_bestaetigung_kein_code_versendet(): void
    {
        config()->set('userauth.two_factor_enabled', true);
        $this->registrieren();
        $this->assertCount(1, $this->nachrichtenAn(self::EMAIL), 'Gegenprobe: Nur die Bestätigungsmail ist unterwegs.');

        $this->anmelden(self::EMAIL)->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertCount(1, $this->nachrichtenAn(self::EMAIL));
    }

    // ------------------------------------------------------------------- AK8

    // AK8
    public function test_ak8_gueltiger_link_bestaetigt_die_email(): void
    {
        $link = $this->registriertMitLink();
        $this->assertFalse($this->bestaetigt(self::EMAIL));
        $this->travelTo(now()->startOfSecond());

        $this->get($link);

        $this->assertTrue($this->bestaetigt(self::EMAIL));
        $this->assertLessThanOrEqual(5, abs(now()->diffInSeconds(Carbon::parse($this->konto(self::EMAIL)->email_verified_at))));
    }

    // AK8
    public function test_ak8_nutzer_landet_auf_der_login_seite_mit_erfolgsmeldung(): void
    {
        $link = $this->registriertMitLink();

        $antwort = $this->get($link);

        $antwort->assertRedirect(route('login'));
        $mitMeldung = $this->ohneToken((string) $this->get($antwort->headers->get('Location'))->assertOk()->getContent());
        $ohneMeldung = $this->ohneToken((string) $this->get('/login')->getContent());
        $this->assertNotSame($ohneMeldung, $mitMeldung, 'Die Login-Seite zeigt keine Erfolgsmeldung.');
        $this->assertGreaterThan(mb_strlen($this->seitentext($ohneMeldung)), mb_strlen($this->seitentext($mitMeldung)));
    }

    // AK8
    public function test_ak8_nach_der_bestaetigung_ist_anmeldung_und_geschuetzte_seite_moeglich(): void
    {
        $link = $this->registriertMitLink();
        $this->anmelden(self::EMAIL);
        $this->assertGuest();

        $this->get($link);

        $this->anmelden(self::EMAIL)->assertRedirect('/');
        $this->assertAuthenticated();
        $this->get('/password/edit')->assertOk();
    }

    // AK8
    public function test_ak8_zweiter_aufruf_aendert_nichts_und_zeigt_dieselbe_meldung(): void
    {
        $link = $this->registriertMitLink();
        $erste = $this->get($link);
        $erste->assertRedirect();
        $ersteSeite = $this->ohneToken((string) $this->get($erste->headers->get('Location'))->getContent());
        $zeitpunkt = $this->konto(self::EMAIL)->email_verified_at;
        $this->assertNotNull($zeitpunkt);
        $this->travel(5)->minutes();

        $zweite = $this->get($link);

        $zweite->assertRedirect($erste->headers->get('Location'));
        $this->assertSame($ersteSeite, $this->ohneToken((string) $this->get($zweite->headers->get('Location'))->getContent()));
        $this->assertEquals($zeitpunkt, $this->konto(self::EMAIL)->email_verified_at);
    }

    // ------------------------------------------------------------------- AK9

    /**
     * @return array<string, array{string}>
     */
    public static function veraenderungen(): array
    {
        return [
            'Signatur verändert' => ['signatur_veraendert'],
            'Signatur entfernt' => ['signatur_entfernt'],
            'Ablaufzeit verändert' => ['ablaufzeit_veraendert'],
            'Pfad verändert (Hash/Konto)' => ['pfad_veraendert'],
            'Konto-ID ausgetauscht' => ['konto_id_ausgetauscht'],
        ];
    }

    // AK9
    #[DataProvider('veraenderungen')]
    public function test_ak9_veraenderter_link_bestaetigt_nichts_und_zeigt_den_hinweis(string $art): void
    {
        $link = $this->registriertMitLink();
        $this->registriertMitLink('anderes@example.test');

        $this->assertLinkUngueltig($this->linkVeraendern($link, $art), self::EMAIL);

        $this->assertFalse($this->bestaetigt(self::EMAIL));
        $this->assertFalse($this->bestaetigt('anderes@example.test'));
        $this->get($link);
        $this->assertTrue($this->bestaetigt(self::EMAIL), 'Gegenprobe: Der unveränderte Link bestätigt das Konto.');
    }

    // AK9
    public function test_ak9_abgelaufener_link_bestaetigt_nichts_und_zeigt_den_hinweis(): void
    {
        $abgelaufen = $this->registriertMitLink('alt@example.test');
        $this->travel(61)->minutes();
        $this->registrieren(['email' => 'frisch@example.test']);
        $frisch = $this->bestaetigungsLink('frisch@example.test');

        $this->assertLinkUngueltig($abgelaufen, 'alt@example.test');

        $this->assertFalse($this->bestaetigt('alt@example.test'));
        $this->get($frisch);
        $this->assertTrue($this->bestaetigt('frisch@example.test'), 'Gegenprobe: Ein frischer Link gilt.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function geloeschteKonten(): array
    {
        return ['soft-gelöscht' => ['soft'], 'endgültig gelöscht (unbekanntes Konto)' => ['hart']];
    }

    // AK9
    #[DataProvider('geloeschteKonten')]
    public function test_ak9_link_auf_geloeschtes_oder_unbekanntes_konto_bestaetigt_nichts_und_zeigt_den_hinweis(string $art): void
    {
        $link = $this->registriertMitLink();
        $this->registriertMitLink('anderes@example.test');
        $konto = User::withTrashed()->findOrFail($this->kontoId(self::EMAIL));
        $art === 'soft' ? $konto->delete() : $konto->forceDelete();

        $this->assertLinkUngueltig($link, self::EMAIL);

        $this->assertFalse($this->bestaetigt('anderes@example.test'));
        if ($art === 'soft') {
            $this->assertNull($this->konto(self::EMAIL)->email_verified_at);
        } else {
            $this->assertSame([], $this->konten(self::EMAIL));
        }
    }

    // ------------------------------------------------------------------ AK10

    // AK10
    public function test_ak10_fuer_ein_konto_mit_offener_bestaetigung_geht_ein_neuer_link_raus(): void
    {
        $this->registrieren();
        $this->travel(61)->seconds();
        $vorher = count($this->nachrichtenAn(self::EMAIL));

        $this->erneutAnfordern(self::EMAIL);

        $nachrichten = $this->nachrichtenAn(self::EMAIL);
        $this->assertCount($vorher + 1, $nachrichten);
        $neu = $this->linkAusMail(end($nachrichten));
        $this->get($neu);
        $this->assertTrue($this->bestaetigt(self::EMAIL), 'Der neue Link bestätigt das Konto.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function andereFaelle(): array
    {
        return [
            'unbekannte Adresse' => ['unbekannt'],
            'schon bestätigt' => ['bestaetigt'],
            'soft-gelöscht' => ['soft'],
            'endgültig gelöscht' => ['hart'],
            'Admin-Konto ohne Bestätigung' => ['admin'],
        ];
    }

    // AK10
    #[DataProvider('andereFaelle')]
    public function test_ak10_ohne_konto_mit_offener_bestaetigung_geht_keine_mail_raus_und_die_antwort_ist_dieselbe(string $fall): void
    {
        $this->registrieren(['email' => 'offen@example.test']);
        $this->travel(61)->seconds();
        $adresse = $fall.'@example.test';
        match ($fall) {
            'bestaetigt' => User::factory()->create(['email' => $adresse]),
            'soft' => User::factory()->create(['email' => $adresse])->delete(),
            'hart' => User::factory()->create(['email' => $adresse])->forceDelete(),
            'admin' => User::factory()->unverified()->create(['email' => $adresse]),
            default => null,
        };
        $offen = $this->antwortMerkmale($this->erneutAnfordern('offen@example.test', '203.0.113.61'));
        $this->assertCount(2, $this->nachrichtenAn('offen@example.test'), 'Gegenprobe: Für das offene Konto geht ein neuer Link raus.');

        $andere = $this->antwortMerkmale($this->erneutAnfordern($adresse, '203.0.113.62'));

        $this->assertSame($offen, $andere);
        $this->assertSame([], $this->nachrichtenAn($adresse));
        $this->assertCount(2, $this->gesendeteNachrichten());
    }

    // AK10
    public function test_ak10_pro_email_geht_hoechstens_ein_link_pro_60_sekunden_raus(): void
    {
        $this->registrieren();
        $this->travel(61)->seconds();
        $erste = $this->antwortMerkmale($this->erneutAnfordern(self::EMAIL));
        $nachErster = count($this->nachrichtenAn(self::EMAIL));

        $zweite = $this->antwortMerkmale($this->erneutAnfordern(self::EMAIL));

        $this->assertCount($nachErster, $this->nachrichtenAn(self::EMAIL));
        $this->assertSame($erste, $zweite, 'Die gedrosselte Antwort muss der normalen gleichen.');
    }

    // AK10
    public function test_ak10_nach_60_sekunden_darf_wieder_ein_link_verschickt_werden(): void
    {
        $this->registrieren();
        $this->travel(61)->seconds();
        $this->erneutAnfordern(self::EMAIL);
        $nachErster = count($this->nachrichtenAn(self::EMAIL));

        $this->travel(61)->seconds();
        $this->erneutAnfordern(self::EMAIL);

        $this->assertCount($nachErster + 1, $this->nachrichtenAn(self::EMAIL));
    }

    // AK10
    public function test_ak10_abweichende_schreibweise_umgeht_die_email_drosselung_nicht(): void
    {
        $this->registrieren();
        $this->travel(61)->seconds();
        $this->erneutAnfordern(self::EMAIL);
        $nachErster = count($this->gesendeteNachrichten());

        $this->erneutAnfordern(strtoupper(self::EMAIL));

        $this->assertCount($nachErster, $this->gesendeteNachrichten());
    }

    // AK10
    public function test_ak10_die_email_drosselung_gilt_nicht_fuer_andere_adressen(): void
    {
        $this->registrieren(['email' => 'a@example.test']);
        $this->registrieren(['email' => 'b@example.test']);
        $this->travel(61)->seconds();

        $this->erneutAnfordern('a@example.test');
        $this->erneutAnfordern('b@example.test');

        $this->assertCount(2, $this->nachrichtenAn('a@example.test'));
        $this->assertCount(2, $this->nachrichtenAn('b@example.test'));
    }

    // AK10
    public function test_ak10_je_ip_hoechstens_5_anfragen_pro_minute(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertNotSame(429, $this->erneutAnfordern("niemand{$i}@example.test", '203.0.113.70')->getStatusCode(), "Anfrage {$i} darf nicht gedrosselt werden.");
        }

        $this->erneutAnfordern('niemand6@example.test', '203.0.113.70')->assertStatus(429);
    }

    // AK10
    public function test_ak10_das_ip_limit_gilt_je_ip_und_endet_nach_einer_minute(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->erneutAnfordern("niemand{$i}@example.test", '203.0.113.71');
        }
        $this->erneutAnfordern('niemand7@example.test', '203.0.113.71')->assertStatus(429);

        $this->assertNotSame(429, $this->erneutAnfordern('niemand8@example.test', '203.0.113.72')->getStatusCode());
        $this->travel(61)->seconds();
        $this->assertNotSame(429, $this->erneutAnfordern('niemand9@example.test', '203.0.113.71')->getStatusCode());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function eingabenFuerErneutAnfordern(): array
    {
        return [
            'E-Mail fehlt' => [[]],
            'E-Mail leer' => [['email' => '']],
            'keine Adresse' => [['email' => 'keine-adresse']],
            'Liste statt Einzelwert' => [['email' => ['a@example.test', 'b@example.test']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $daten
     */
    // AK10
    #[DataProvider('eingabenFuerErneutAnfordern')]
    public function test_ak10_ungueltige_eingabe_ergibt_keinen_serverfehler_und_keine_mail(array $daten): void
    {
        $this->registrieren();
        $this->travel(61)->seconds();
        $vorher = count($this->gesendeteNachrichten());
        $route = '/'.ltrim($this->erneutAnfordernRoute()->uri(), '/');

        $antwort = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.80'])->from('/login')->post($route, $daten);

        $this->assertLessThan(500, $antwort->getStatusCode());
        if ($antwort->isRedirect()) {
            $this->assertLessThan(500, $this->get($antwort->headers->get('Location'))->getStatusCode());
        }
        $this->assertCount($vorher, $this->gesendeteNachrichten());
    }

    // ------------------------------------------------------------------ AK13

    // AK13
    public function test_ak13_bestaetigen_schreibt_einen_audit_eintrag_email_verified(): void
    {
        $this->auditTabelleBereitstellen();
        config()->set('userauth.audit_log_enabled', true);
        $link = $this->registriertMitLink();
        $vorher = DB::table('user_auth_audit_logs')->where('event', 'email_verified')->count();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.90'])->withHeader('User-Agent', 'Bestaetigung/1.0')->get($link);

        $eintraege = DB::table('user_auth_audit_logs')->where('event', 'email_verified')->get();
        $this->assertCount($vorher + 1, $eintraege);
        $this->assertSame($this->kontoId(self::EMAIL), (int) $eintraege[0]->user_id);
        $this->assertSame(self::EMAIL, $eintraege[0]->email);
        $this->assertSame('203.0.113.90', $eintraege[0]->ip_address);
        $this->assertSame('Bestaetigung/1.0', $eintraege[0]->user_agent);
    }

    // AK13
    public function test_ak13_zweiter_aufruf_und_ungueltiger_link_schreiben_keinen_weiteren_eintrag(): void
    {
        $this->auditTabelleBereitstellen();
        config()->set('userauth.audit_log_enabled', true);
        $link = $this->registriertMitLink();
        $this->get($this->linkVeraendern($link, 'signatur_veraendert'));
        $this->assertSame(0, DB::table('user_auth_audit_logs')->where('event', 'email_verified')->count());
        $this->get($link);
        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'email_verified')->count(), 'Gegenprobe: Der erste gültige Aufruf schreibt den Eintrag.');

        $this->get($link);

        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'email_verified')->count());
    }

    // AK13
    public function test_ak13_ist_das_audit_log_aus_gibt_es_keinen_eintrag(): void
    {
        $this->auditTabelleBereitstellen();
        config()->set('userauth.audit_log_enabled', false);
        $link = $this->registriertMitLink();

        $this->get($link);

        $this->assertTrue($this->bestaetigt(self::EMAIL), 'Gegenprobe: Die Bestätigung wurde durchgeführt.');
        $this->assertSame(0, DB::table('user_auth_audit_logs')->count());
    }

    // -------------------------------------------------------------- Randfälle

    // Randfall 2
    public function test_randfall2_link_nach_geaenderter_email_ist_ungueltig(): void
    {
        $link = $this->registriertMitLink();
        DB::table('users')->where('email', self::EMAIL)->update(['email' => 'neu@example.test']);

        $this->assertLinkUngueltig($link, 'neu@example.test');

        $this->assertFalse($this->bestaetigt('neu@example.test'));
        DB::table('users')->where('email', 'neu@example.test')->update(['email' => self::EMAIL]);
        $this->get($link);
        $this->assertTrue($this->bestaetigt(self::EMAIL), 'Gegenprobe: Mit der ursprünglichen E-Mail gilt der Link.');
    }

    // Randfall 3
    public function test_randfall3_social_login_mit_bestaetigter_adresse_setzt_email_verified_at_und_beendet_die_bestaetigung(): void
    {
        config()->set('userauth.social.enabled', true);
        config()->set('userauth.social.providers', ['google']);
        $provider = new FakeSocialIdentityProvider;
        $this->app->instance(SocialIdentityProvider::class, $provider);
        $this->registrieren();
        $this->anmelden(self::EMAIL);
        $this->assertGuest();
        $this->assertFalse($this->bestaetigt(self::EMAIL), 'Gegenprobe: Zuerst ist die Bestätigung offen.');
        $provider->returnIdentity('google-sub-1', self::EMAIL);
        $this->get(route('userauth.social.callback', 'google'));
        $url = null;
        Mail::assertQueued(SocialLinkConfirmation::class, function (SocialLinkConfirmation $mail) use (&$url): bool {
            $url = $mail->confirmUrl;

            return true;
        });
        $token = basename((string) parse_url((string) $url, PHP_URL_PATH));

        $this->post(route('userauth.social.confirm.store', $token));

        $this->assertTrue($this->bestaetigt(self::EMAIL));
        $this->anmelden(self::EMAIL)->assertRedirect('/');
        $this->assertAuthenticated();
        $this->get('/password/edit')->assertOk();
    }

    // Randfall 4
    public function test_randfall4_vom_admin_geloeschtes_konto_hat_ungueltigen_link_und_bekommt_keine_neue_mail(): void
    {
        $link = $this->registriertMitLink('weg@example.test');
        $this->registrieren(['email' => 'bleibt@example.test']);
        User::findOrFail($this->kontoId('weg@example.test'))->delete();

        $this->assertLinkUngueltig($link, 'weg@example.test');

        $this->travel(61)->seconds();
        $this->erneutAnfordern('weg@example.test', '203.0.113.95');
        $this->erneutAnfordern('bleibt@example.test', '203.0.113.96');
        $this->assertCount(1, $this->nachrichtenAn('weg@example.test'));
        $this->assertCount(2, $this->nachrichtenAn('bleibt@example.test'), 'Gegenprobe: Ein anderes offenes Konto bekommt den Link.');
    }

    // Randfall 5
    public function test_randfall5_ausgeschaltete_registrierung_laesst_unterwegs_befindliche_links_ins_leere_laufen(): void
    {
        $link = $this->registriertMitLink();
        $this->assertTrue(URL::hasValidSignature(Request::create($link)), 'Gegenprobe: Der Link ist gültig, solange die Registrierung an ist.');
        $this->get('/register')->assertOk();

        $this->neuStarten(['enabled' => false]);

        $this->get('/register')->assertNotFound();
        $this->get($link)->assertNotFound();
        $this->assertFalse($this->bestaetigt(self::EMAIL));
        $this->assertCount(1, $this->konten(self::EMAIL));
    }

    // Randfall 6
    public function test_randfall6_mit_zwei_faktor_laeuft_die_anmeldung_nach_der_bestaetigung_ueber_den_code(): void
    {
        config()->set('userauth.two_factor_enabled', true);
        $link = $this->registriertMitLink();
        $this->assertGuest();
        $this->assertCount(1, $this->nachrichtenAn(self::EMAIL), 'Gegenprobe: Die Registrierung schickt keinen Code.');
        $this->get($link);

        $this->anmelden(self::EMAIL)->assertRedirect(route('verify.index'));

        $this->assertGuest();
        $this->assertCount(2, $this->nachrichtenAn(self::EMAIL), 'Der Code kommt per Mail.');
        $code = $this->zweiFaktorCode(self::EMAIL);
        $this->from(route('verify.index'))->post(route('verify.store'), ['two_factor_code' => $code]);
        $this->assertAuthenticated();
    }
}
