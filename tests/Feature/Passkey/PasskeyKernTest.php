<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Tests\Support\PasskeyGeraet;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Kern der Passkey-Anmeldung über HTTP mit echter Kryptoprüfung: Schalter und Routen (AK1, AK17), Anmeldeoptionen
 * (AK3), fehlerhafte Anmeldungen (AK6), keine Geheimnisse in Meldung und Log (AK18). Erfolgreiche Anmeldung und
 * Benutzerregeln: PasskeyAnmeldenTest.
 */
final class PasskeyKernTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
    }

    protected function tearDown(): void
    {
        $this->passkeyAbbauen();

        parent::tearDown();
    }

    /** @return array<string, array{0: string, 1: string, 2: list<int>}> */
    public static function routen(): array
    {
        return [
            'login.options' => ['POST', 'userauth.passkey.login.options', []],
            'login' => ['POST', 'userauth.passkey.login', []],
            'register.options' => ['POST', 'userauth.passkey.register.options', []],
            'register' => ['POST', 'userauth.passkey.register', []],
            'index' => ['GET', 'userauth.passkey.index', []],
            'destroy' => ['DELETE', 'userauth.passkey.destroy', [1]],
        ];
    }

    /**
     * @param  list<int>  $parameter
     */
    // AK1
    #[DataProvider('routen')]
    public function test_ak1_ohne_passkey_antworten_alle_routen_mit_404(string $methode, string $name, array $parameter): void
    {
        config()->set('userauth.passkey.enabled', false);
        $this->assertTrue(Route::has($name), 'Die Route muss immer registriert sein.');

        $this->json($methode, route($name, $parameter))->assertNotFound();
        $this->actingAs($this->nutzer())->json($methode, route($name, $parameter))->assertNotFound();

        // Gegenprobe: mit aktivem Passkey antwortet dieselbe Route für einen Gast nicht mit 404
        $this->neueSitzung();
        config()->set('userauth.passkey.enabled', true);
        $this->assertNotSame(404, $this->json($methode, route($name, $parameter))->status());
    }

    // AK17
    public function test_ak17_routen_tragen_die_namen_und_pfade(): void
    {
        $pfade = [
            'userauth.passkey.login.options' => '/passkey/login/options',
            'userauth.passkey.login' => '/passkey/login',
            'userauth.passkey.register.options' => '/passkey/register/options',
            'userauth.passkey.register' => '/passkey/register',
            'userauth.passkey.index' => '/passkey',
        ];
        foreach ($pfade as $name => $pfad) {
            $this->assertSame($pfad, route($name, [], false), $name);
        }
        $this->assertSame('/passkey/5', route('userauth.passkey.destroy', 5, false));
    }

    // AK17
    #[DataProvider('verwaltungsRouten')]
    public function test_ak17_verwaltungsrouten_verlangen_eine_anmeldung(string $methode, string $name, array $parameter): void
    {
        $gast = $this->json($methode, route($name, $parameter));
        $this->assertContains($gast->status(), [302, 401], 'Gast erreicht '.$name);
        $this->assertSame(0, $this->anzahlPasskeys());

        // Gegenprobe: angemeldet kommt dieselbe Route durch (nicht 401/302 auf die Login-Seite)
        $this->actingAs($this->nutzer());
        $this->assertNotContains($this->json($methode, route($name, $parameter))->status(), [401, 302]);
    }

    /** @return array<string, array{0: string, 1: string, 2: list<int>}> */
    public static function verwaltungsRouten(): array
    {
        return array_diff_key(self::routen(), ['login.options' => 1, 'login' => 1]);
    }

    // AK17
    public function test_ak17_anmeldung_per_passkey_ist_nur_fuer_gaeste_erreichbar(): void
    {
        [$fremder, $geraetFremder] = $this->nutzerMitPasskey(['email' => 'fremd@firma.de']);
        $angemeldet = $this->nutzer(['email' => 'ich@firma.de']);
        $this->passkeyAnmelden($geraetFremder)->assertOk(); // Gegenprobe: derselbe Aufbau meldet einen Gast an
        $this->assertAuthenticatedAs($fremder);
        $this->neueSitzung();

        $optionen = $this->loginOptionen(); // Challenge der Sitzung, solange noch Gast
        $this->actingAs($angemeldet);
        $antwort = $this->postJson(route('userauth.passkey.login'), $geraetFremder->anmeldung($optionen));

        $this->assertAuthenticatedAs($angemeldet);
        $this->assertTrue($antwort->isRedirect() || $antwort->json('redirect') === '/', 'Weder Weiterleitung noch JSON auf redirect_after_login.');
    }

    /** @return array<string, array{0: string}> */
    public static function gedrosselteRouten(): array
    {
        return ['options' => ['userauth.passkey.login.options'], 'login' => ['userauth.passkey.login']];
    }

    // AK17
    #[DataProvider('gedrosselteRouten')]
    public function test_ak17_login_routen_sind_gedrosselt_wie_die_oidc_routen(string $name): void
    {
        $this->assertNotSame(429, $this->postJson(route($name))->status());

        $gedrosselt = null;
        for ($i = 2; $i <= 40; $i++) {
            if ($this->postJson(route($name))->status() === 429) {
                $gedrosselt = $i;
                break;
            }
        }

        $this->assertNotNull($gedrosselt, 'Auch nach 40 Aufrufen in einer Minute kommt kein 429.');
        $this->assertLessThanOrEqual(31, $gedrosselt);
    }

    // AK3
    public function test_ak3_optionen_enthalten_challenge_rpid_timeout_und_verlangen_benutzerverifikation(): void
    {
        $optionen = $this->postJson(route('userauth.passkey.login.options'))->assertOk()->json();

        $this->assertGreaterThanOrEqual(16, strlen(PasskeyGeraet::b64uDecode($optionen['challenge'])));
        $this->assertSame(self::RP_ID, $optionen['rpId']);
        $this->assertGreaterThan(0, $optionen['timeout']);
        $this->assertSame('required', $optionen['userVerification']);
        $this->assertSame([], $optionen['allowCredentials']);

        // Quelle: rpId aus der Konfiguration
        config()->set('userauth.passkey.rp_id', 'example.test');
        $this->assertSame('example.test', $this->loginOptionen()['rpId']);
    }

    // AK3
    public function test_ak3_zweite_challenge_ersetzt_die_erste(): void
    {
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'a@firma.de']);
        $erste = $this->loginOptionen();
        $zweite = $this->loginOptionen();

        $this->assertNotSame($erste['challenge'], $zweite['challenge']);
        $this->assertPasskeyAbgelehnt($this->passkeyAnmelden($geraet, [], $erste));

        // Gegenprobe: mit der Challenge einer neuen Optionen-Antwort gelingt die Anmeldung desselben Geräts
        $this->passkeyAnmelden($geraet)->assertOk();
        $this->assertAuthenticatedAs($nutzer);
    }

    /** @return array<string, array{0: string}> */
    public static function fehlerfaelle(): array
    {
        $faelle = ['challenge_fehlt', 'challenge_abgelaufen', 'challenge_falsch', 'origin_falsch', 'rpid_falsch', 'signatur_ungueltig',
            'credential_unbekannt', 'benutzerverifikation_fehlt', 'kein_json', 'leer', 'ohne_response'];

        return array_combine($faelle, array_map(fn (string $fall): array => [$fall], $faelle));
    }

    // AK6
    #[DataProvider('fehlerfaelle')]
    public function test_ak6_fehlerhafte_anmeldung_meldet_nicht_an_und_verbraucht_die_challenge(string $fall): void
    {
        Carbon::setTestNow('2031-03-14 10:00:00');
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'a@firma.de']);
        $optionen = $fall === 'challenge_fehlt'
            ? ['challenge' => PasskeyGeraet::b64u(random_bytes(32))]
            : $this->loginOptionen();
        if ($fall === 'challenge_abgelaufen') {
            Carbon::setTestNow(now()->addMinutes(5)->addSecond());
        }

        $uri = route('userauth.passkey.login');
        $kopf = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        $antwort = match ($fall) {
            'kein_json' => $this->call('POST', $uri, [], [], [], $kopf, 'das ist kein json'),
            'leer' => $this->postJson($uri, []),
            'ohne_response' => $this->postJson($uri, ['id' => $geraet->credentialIdB64u(), 'type' => 'public-key']),
            'credential_unbekannt' => $this->postJson($uri, $this->geraet()->anmeldung($optionen)),
            default => $this->postJson($uri, $geraet->anmeldung($optionen, match ($fall) {
                'challenge_falsch' => ['challenge' => PasskeyGeraet::b64u(random_bytes(32))],
                'origin_falsch' => ['origin' => 'https://evil.example'],
                'rpid_falsch' => ['rpId' => 'evil.example.test'],
                'signatur_ungueltig' => ['fremderSchluessel' => true],
                'benutzerverifikation_fehlt' => ['flags' => PasskeyGeraet::FLAG_UP],
                default => [],
            })),
        };

        $this->assertPasskeyAbgelehnt($antwort);
        $this->assertNull(DB::table('user_passkeys')->value('last_used_at'));

        // Challenge verbraucht: dieselbe Challenge mit gültiger Assertion scheitert ebenfalls
        $this->assertPasskeyAbgelehnt($this->postJson($uri, $geraet->anmeldung($optionen)));

        // Gegenprobe: Benutzer, Passkey und Gerät sind in Ordnung, mit neuer Challenge klappt die Anmeldung
        $this->passkeyAnmelden($geraet)->assertOk();
        $this->assertAuthenticatedAs($nutzer);
    }

    // AK6
    public function test_ak6_challenge_gilt_fuenf_minuten(): void
    {
        Carbon::setTestNow('2031-03-14 10:00:00');
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'a@firma.de']);
        $optionen = $this->loginOptionen();

        Carbon::setTestNow(now()->addMinutes(4)->addSeconds(50));
        $this->passkeyAnmelden($geraet, [], $optionen)->assertOk();
        $this->assertAuthenticatedAs($nutzer);
    }

    // AK6
    public function test_ak6_klon_verdacht_bei_zaehler_kleiner_oder_gleich_dem_gespeicherten(): void
    {
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'a@firma.de']);

        // gespeicherter Zähler 0 (Gerät ohne Zähler): Zähler 0 ist gültig
        foreach ([0, 0, 7] as $zaehler) {
            $this->passkeyAnmelden($geraet, ['counter' => $zaehler])->assertOk();
            $this->assertAuthenticatedAs($nutzer);
            $this->neueSitzung();
        }

        // gespeichert 7: gleich und kleiner werden abgelehnt, größer gelingt
        $this->assertPasskeyAbgelehnt($this->passkeyAnmelden($geraet, ['counter' => 7]));
        $this->assertPasskeyAbgelehnt($this->passkeyAnmelden($geraet, ['counter' => 3]));
        $this->passkeyAnmelden($geraet, ['counter' => 8])->assertOk();
        $this->assertAuthenticatedAs($nutzer);
    }

    // AK6
    public function test_ak6_meldung_ist_allgemein_uebersetzt_und_fuer_alle_fehlerarten_gleich(): void
    {
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'a@firma.de']);

        $meldungen = [
            $this->passkeyAnmelden($geraet, ['fremderSchluessel' => true])->json('message'),
            $this->passkeyAnmelden($geraet, ['origin' => 'https://evil.example'])->json('message'),
            $this->passkeyAnmelden($this->geraet())->json('message'),
        ];
        $this->assertCount(1, array_unique($meldungen), 'Die Meldung verrät die Fehlerart.');
        $this->assertStringNotContainsString('userauth::', $meldungen[0], 'Übersetzungsschlüssel statt Text.');

        app()->setLocale('en');
        $englisch = $this->passkeyAnmelden($geraet, ['fremderSchluessel' => true])->json('message');
        $this->assertNotSame($meldungen[0], $englisch);
        $this->assertStringNotContainsString('userauth::', $englisch);
    }

    // AK18
    public function test_ak18_weder_antwort_noch_log_enthalten_challenge_assertion_oder_schluessel(): void
    {
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'a@firma.de']);
        $this->logSammeln();

        $optionen = $this->loginOptionen();
        $assertion = $geraet->anmeldung($optionen, ['fremderSchluessel' => true]);
        $antwort = $this->postJson(route('userauth.passkey.login'), $assertion);
        $this->assertPasskeyAbgelehnt($antwort);

        $abgelaufen = $this->loginOptionen();
        Carbon::setTestNow(now()->addMinutes(6));
        $zweite = $geraet->anmeldung($abgelaufen);
        $antwortAbgelaufen = $this->postJson(route('userauth.passkey.login'), $zweite);
        $this->assertPasskeyAbgelehnt($antwortAbgelaufen);

        $geheimnisse = [$optionen['challenge'], $abgelaufen['challenge'], ...array_values($assertion['response']),
            ...array_values($zweite['response']), ...$geraet->oeffentlicherSchluessel()];
        $heuhaufen = $antwort->getContent().$antwortAbgelaufen->getContent().implode("\n", $this->logTexte);
        $this->assertNotEmpty($this->logTexte, 'Der Fehler wird nicht geloggt (Fehlerart fehlt im Log).');
        foreach ($geheimnisse as $geheimnis) {
            $this->assertStringNotContainsString((string) $geheimnis, $heuhaufen);
        }
    }
}
