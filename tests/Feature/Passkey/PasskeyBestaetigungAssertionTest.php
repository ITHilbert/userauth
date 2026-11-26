<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\PasskeyGeraet;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Bestätigung mit einem vorhandenen Passkey (userauth passkey-bestaetigung, #2225), mit echter Kryptoprüfung:
 * Optionen (AK5), Assertion und Fehlerfälle (AK6), Impersonate (AK9). Die Assertion geht wie bei der Anmeldung als
 * `PublicKeyCredential.toJSON()` im Body (oberste Ebene).
 */
final class PasskeyBestaetigungAssertionTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    private int $anmeldungen = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
        Event::listen(Login::class, function (): void {
            $this->anmeldungen++;
        });
    }

    protected function tearDown(): void
    {
        $this->passkeyAbbauen();

        parent::tearDown();
    }

    /**
     * Benutzer mit einem Passkey (Aufbau mit Bestätigung per Passwort), danach ohne Bestätigung angemeldet.
     *
     * @param  array<string, mixed>  $attribute
     * @return array{0: User, 1: PasskeyGeraet}
     */
    private function nutzerAnmelden(array $attribute = []): array
    {
        $this->passkeyAutoBestaetigen = true;
        [$nutzer, $geraet] = $this->nutzerMitPasskey($attribute);
        $this->passkeyAutoBestaetigen = false;
        $this->anmeldungen = 0;
        $this->actingAs($nutzer);

        return [$nutzer, $geraet];
    }

    /** @return array<string, mixed> */
    private function bestaetigungsOptionen(): array
    {
        $optionen = $this->postJson(route('userauth.passkey.bestaetigen.optionen'))->assertOk()->json();
        $this->assertNotEmpty($optionen['challenge'] ?? null, 'Die Optionen enthalten keine Challenge.');

        return $optionen;
    }

    /**
     * @param  array<string, mixed>  $optionen
     * @param  array<string, mixed>  $abweichung
     */
    private function assertionSenden(PasskeyGeraet $geraet, array $optionen, array $abweichung = []): TestResponse
    {
        return $this->postJson(route('userauth.passkey.bestaetigen.assertion'), $geraet->anmeldung($optionen, $abweichung));
    }

    private function assertBestaetigt(User $nutzer): void
    {
        $this->passkeyRegistrieren($nutzer, $this->geraet(), 'Zusatz')->assertSuccessful();
    }

    private function assertNichtBestaetigt(): void
    {
        $antwort = $this->postJson(route('userauth.passkey.register.options'))->assertForbidden();
        $this->assertTrue($antwort->json('bestaetigung_erforderlich'));
    }

    // AK5
    public function test_ak5_optionen_nur_mit_den_passkeys_dieses_benutzers(): void
    {
        [$nutzer, $erstes] = $this->nutzerAnmelden(['email' => 'max@firma.de']);
        $anderer = $this->nutzer(['email' => 'anderer@firma.de']);
        $fremdes = $this->passkeyZusatz($anderer);
        $zweites = $this->passkeyZusatz($nutzer);
        $this->actingAs($nutzer);

        $optionen = $this->bestaetigungsOptionen();

        $ids = array_column($optionen['allowCredentials'], 'id');
        sort($ids);
        $erwartet = [$erstes->credentialIdB64u(), $zweites->credentialIdB64u()];
        sort($erwartet);
        $this->assertSame($erwartet, $ids, 'allowCredentials enthält nicht genau die Passkeys des Benutzers.');
        $this->assertNotContains($fremdes->credentialIdB64u(), $ids);
        $this->assertNotSame($optionen['challenge'], $this->bestaetigungsOptionen()['challenge'], 'Die Challenge wird nicht je Aufruf neu erzeugt.');
    }

    /** Zusätzlicher Passkey für einen Benutzer; danach ist niemand angemeldet. */
    private function passkeyZusatz(User $nutzer): PasskeyGeraet
    {
        $this->passkeyAutoBestaetigen = true;
        $geraet = $this->passkeyHinzufuegen($nutzer, 'Zusatzgerät');
        $this->passkeyAutoBestaetigen = false;

        return $geraet;
    }

    // AK5
    public function test_ak5_challenge_der_bestaetigung_ist_getrennt_von_der_registrierung(): void
    {
        [$nutzer, $geraet] = $this->nutzerAnmelden();
        $this->postJson(route('userauth.passkey.bestaetigen'), ['aktuelles_passwort' => 'password'])->assertNoContent();

        // Registrierungsoptionen nach den Bestätigungsoptionen ersetzen deren Challenge nicht
        $bestaetigung = $this->bestaetigungsOptionen();
        $this->registrierungsOptionen();
        $this->assertionSenden($geraet, $bestaetigung)->assertNoContent();

        // eine Registrierung mit der Challenge der Bestätigung scheitert, ebenso eine Assertion auf die der Registrierung
        $bestaetigung = $this->bestaetigungsOptionen();
        $registrierung = $this->registrierungsOptionen();
        $this->passkeyRegistrieren($nutzer, $this->geraet(), 'Falsch', [], $bestaetigung)->assertStatus(422);
        $this->assertionSenden($geraet, $registrierung)->assertStatus(422);
        $this->assertSame(1, $this->anzahlPasskeys($nutzer->getKey()), 'Mit der falschen Challenge entstand ein Passkey.');
    }

    // AK6
    public function test_ak6_gueltige_assertion_204_bestaetigt_bleibt_angemeldet_und_verbraucht_die_challenge(): void
    {
        [$nutzer, $geraet] = $this->nutzerAnmelden();
        $this->assertNichtBestaetigt();
        $optionen = $this->bestaetigungsOptionen();

        $antwort = $this->assertionSenden($geraet, $optionen)->assertNoContent();

        $this->assertSame('', $antwort->getContent());
        $this->assertAuthenticatedAs($nutzer);
        $this->assertSame(0, $this->anzahlAnmeldungen(), 'Die Bestätigung hat ein Login-Ereignis ausgelöst.');
        $this->assertBestaetigt($nutzer);

        // Challenge verbraucht, auch bei Erfolg: dieselbe Assertion noch einmal wird abgelehnt
        $this->postJson(route('userauth.passkey.bestaetigen.assertion'), $geraet->anmeldung($optionen, ['counter' => $geraet->zaehler + 1]))->assertStatus(422);
        $this->assertAuthenticatedAs($nutzer);
    }

    private function anzahlAnmeldungen(): int
    {
        return $this->anmeldungen;
    }

    /** @return array<string, array{0: string}> */
    public static function fehlerfaelle(): array
    {
        $faelle = ['passkey_eines_anderen_benutzers', 'challenge_fehlt', 'challenge_abgelaufen', 'challenge_falsch', 'signatur_ungueltig', 'origin_falsch'];

        return array_combine($faelle, array_map(fn (string $fall): array => [$fall], $faelle));
    }

    // AK6
    #[DataProvider('fehlerfaelle')]
    public function test_ak6_fehlerhafte_assertion_422_ohne_bestaetigung_challenge_verbraucht_benutzer_bleibt_angemeldet(string $fall): void
    {
        Carbon::setTestNow('2031-03-14 10:00:00');
        $fremder = $this->nutzer(['email' => 'fremder@firma.de']);
        $fremdesGeraet = $this->passkeyZusatz($fremder);
        [$nutzer, $geraet] = $this->nutzerAnmelden(['email' => 'max@firma.de']);
        $optionen = $fall === 'challenge_fehlt' ? ['challenge' => PasskeyGeraet::b64u(random_bytes(32))] : $this->bestaetigungsOptionen();
        if ($fall === 'challenge_abgelaufen') {
            Carbon::setTestNow(now()->addMinutes(6));
        }

        $antwort = $this->assertionSenden($fall === 'passkey_eines_anderen_benutzers' ? $fremdesGeraet : $geraet, $optionen, match ($fall) {
            'challenge_falsch' => ['challenge' => PasskeyGeraet::b64u(random_bytes(32))],
            'signatur_ungueltig' => ['fremderSchluessel' => true],
            'origin_falsch' => ['origin' => 'https://evil.example'],
            default => [],
        });

        $antwort->assertStatus(422);
        $this->assertIsString($antwort->json('message'));
        $this->assertNotSame('', trim((string) $antwort->json('message')));
        $this->assertAuthenticatedAs($nutzer);
        $this->assertSame(0, $this->anzahlAnmeldungen());
        $this->assertNichtBestaetigt();

        // Challenge verbraucht: auch die gültige Assertion auf dieselbe Challenge scheitert
        if (! in_array($fall, ['challenge_fehlt', 'challenge_abgelaufen'], true)) {
            $this->assertionSenden($geraet, $optionen)->assertStatus(422);
            $this->assertNichtBestaetigt();
        }

        // Gegenprobe: mit frischer Challenge bestätigt derselbe Passkey
        $this->assertionSenden($geraet, $this->bestaetigungsOptionen())->assertNoContent();
        $this->assertBestaetigt($nutzer);
    }

    // AK9
    public function test_ak9_assertion_unter_impersonate_403_auch_mit_gueltiger_assertion_ohne_bestaetigung(): void
    {
        [$admin, $geraet] = $this->nutzerAnmelden(['email' => 'admin@firma.de', 'role_id' => 2]);
        $ziel = $this->nutzer(['email' => 'ziel@firma.de']);
        $optionen = $this->bestaetigungsOptionen();
        $this->post(route('userauth.impersonate', $ziel->getKey()))->assertRedirect();
        $this->assertSame($admin->getKey(), session('impersonated_by'), 'Der Aufbau greift nicht: keine Impersonate-Sitzung.');

        $this->assertionSenden($geraet, $optionen)->assertForbidden();

        $this->post(route('userauth.impersonate.leave'))->assertRedirect();
        $this->assertAuthenticatedAs($admin);
        $this->assertNichtBestaetigt();
        // Gegenprobe: ohne Impersonate bestätigt derselbe Passkey
        $this->assertionSenden($geraet, $this->bestaetigungsOptionen())->assertNoContent();
        $this->assertBestaetigt($admin);
    }
}
