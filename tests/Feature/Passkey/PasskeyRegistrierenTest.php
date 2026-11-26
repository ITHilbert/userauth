<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Support\PasskeyGeraet;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Registrierung eines Passkeys durch einen angemeldeten Benutzer, mit echter Kryptoprüfung: Registrierungsoptionen
 * (AK9), Speichern und Fehlerfälle (AK10), keine Geheimnisse in Meldung und Log (AK18).
 */
final class PasskeyRegistrierenTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
    }

    private function assertRegistrierungAbgelehnt(TestResponse $antwort): void
    {
        $antwort->assertStatus(422);
        $this->assertIsString($antwort->json('message'));
        $this->assertNotSame('', trim((string) $antwort->json('message')));
    }

    // AK9
    public function test_ak9_optionen_enthalten_rp_stabiles_handle_algorithmen_auswahl_und_ausschlussliste(): void
    {
        config()->set('userauth.passkey.rp_name', 'Meine App');
        $nutzer = $this->nutzer(['email' => 'max@firma.de']);
        $anderer = $this->nutzer(['email' => 'anderer@firma.de']);
        $eigenes = $this->passkeyHinzufuegen($nutzer, 'Handy');
        $fremdes = $this->passkeyHinzufuegen($anderer, 'Fremd');

        $this->actingAs($nutzer);
        $optionen = $this->registrierungsOptionen();

        $this->assertSame('Meine App', $optionen['rp']['name']);
        $this->assertSame(self::RP_ID, $optionen['rp']['id']);
        $handle = PasskeyGeraet::b64uDecode($optionen['user']['id']);
        $this->assertNotSame('', $handle);
        $this->assertStringNotContainsString('max@firma.de', $handle.$optionen['user']['id']);
        $this->assertSame($optionen['user']['id'], $this->registrierungsOptionen()['user']['id'], 'Das Handle ist nicht stabil.');
        $this->actingAs($anderer);
        $this->assertNotSame($optionen['user']['id'], $this->registrierungsOptionen()['user']['id']);
        $this->actingAs($nutzer);

        $algorithmen = array_column($optionen['pubKeyCredParams'], 'alg');
        $this->assertContains(-7, $algorithmen, 'ES256 fehlt.');
        $this->assertContains(-257, $algorithmen, 'RS256 fehlt.');
        $this->assertSame('required', $optionen['authenticatorSelection']['residentKey']);
        $this->assertSame('required', $optionen['authenticatorSelection']['userVerification']);
        $this->assertSame('none', $optionen['attestation']);
        $this->assertNotEmpty($optionen['challenge']);

        $ausgeschlossen = array_column($optionen['excludeCredentials'], 'id');
        $this->assertSame([$eigenes->credentialIdB64u()], $ausgeschlossen, 'Nur die eigenen Passkeys gehören in excludeCredentials.');
        $this->assertNotContains($fremdes->credentialIdB64u(), $ausgeschlossen);
    }

    // AK9
    public function test_ak9_rp_name_ohne_konfiguration_ist_der_app_name(): void
    {
        $this->actingAs($this->nutzer());

        $this->assertSame('Testapp', $this->registrierungsOptionen()['rp']['name']);
    }

    // AK10
    public function test_ak10_gueltige_antwort_speichert_den_passkey_zum_angemeldeten_benutzer_und_verbraucht_die_challenge(): void
    {
        $nutzer = $this->nutzer(['email' => 'max@firma.de']);
        $anderer = $this->nutzer(['email' => 'anderer@firma.de']);
        $geraet = $this->geraet();
        $this->actingAs($nutzer);
        $optionen = $this->registrierungsOptionen();

        $antwort = $this->passkeyRegistrieren($nutzer, $geraet, 'Mein Handy', [], $optionen, ['user_id' => $anderer->getKey()])->assertSuccessful();

        $this->assertNotNull($antwort->json());
        $zeile = DB::table('user_passkeys')->first();
        $this->assertSame(1, $this->anzahlPasskeys());
        $this->assertSame($nutzer->getKey(), (int) $zeile->user_id);
        $this->assertSame('Mein Handy', $zeile->name);
        $this->assertNotEmpty($zeile->credential_id);
        $this->assertNotEmpty($zeile->credential_source);
        $this->assertNull($zeile->last_used_at);

        // Challenge verbraucht: dieselbe Antwort noch einmal wird abgelehnt, es bleibt bei einer Zeile
        $this->assertRegistrierungAbgelehnt($this->passkeyRegistrieren($nutzer, $geraet, 'Noch einmal', [], $optionen));
        $this->assertSame(1, $this->anzahlPasskeys());

        // der registrierte Passkey meldet den Benutzer später an (Gegenprobe: Kryptografie stimmt durchgängig)
        $this->neueSitzung();
        $this->passkeyAnmelden($geraet)->assertOk();
        $this->assertAuthenticatedAs($nutzer);
    }

    // AK10
    public function test_ak10_name_hoechstens_100_zeichen_leer_oder_fehlend_ist_passkey(): void
    {
        $nutzer = $this->nutzer();

        $this->passkeyRegistrieren($nutzer, $this->geraet(), '')->assertSuccessful();
        $this->passkeyRegistrieren($nutzer, $this->geraet(), str_repeat('n', 100))->assertSuccessful();
        $ohneName = $this->geraet();
        $this->actingAs($nutzer);
        $this->postJson(route('userauth.passkey.register'), ['credential' => $ohneName->registrierung($this->registrierungsOptionen())])->assertSuccessful();
        $this->passkeyRegistrieren($nutzer, $this->geraet(), str_repeat('z', 150));

        $namen = DB::table('user_passkeys')->orderBy('id')->pluck('name')->all();
        $this->assertSame('Passkey', $namen[0]);
        $this->assertSame(str_repeat('n', 100), $namen[1]);
        $this->assertSame('Passkey', $namen[2]);
        foreach ($namen as $name) {
            $this->assertLessThanOrEqual(100, mb_strlen($name), 'Ein Name ist länger als 100 Zeichen.');
        }
    }

    /** @return array<string, array{0: string}> */
    public static function fehlerfaelle(): array
    {
        $faelle = ['challenge_fehlt', 'challenge_falsch', 'origin_falsch', 'rpid_falsch', 'benutzerverifikation_fehlt', 'kein_json', 'credential_schon_eingetragen'];

        return array_combine($faelle, array_map(fn (string $fall): array => [$fall], $faelle));
    }

    // AK10
    #[DataProvider('fehlerfaelle')]
    public function test_ak10_fehlerhafte_registrierung_speichert_nichts_und_verbraucht_die_challenge(string $fall): void
    {
        $nutzer = $this->nutzer(['email' => 'max@firma.de']);
        $anderer = $this->nutzer(['email' => 'anderer@firma.de']);
        $geraet = $this->geraet();
        $vorhanden = $fall === 'credential_schon_eingetragen' ? $this->passkeyHinzufuegen($anderer, 'Fremd') : null;
        $vorher = $this->anzahlPasskeys();
        $this->actingAs($nutzer);
        $optionen = $fall === 'challenge_fehlt' ? ['challenge' => PasskeyGeraet::b64u(random_bytes(32))] : $this->registrierungsOptionen();

        $uri = route('userauth.passkey.register');
        $kopf = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        $antwort = $fall === 'kein_json'
            ? $this->call('POST', $uri, [], [], [], $kopf, 'das ist kein json')
            : $this->passkeyRegistrieren($nutzer, $vorhanden ?? $geraet, 'Handy', match ($fall) {
                'challenge_falsch' => ['challenge' => PasskeyGeraet::b64u(random_bytes(32))],
                'origin_falsch' => ['origin' => 'https://evil.example'],
                'rpid_falsch' => ['rpId' => 'evil.example.test'],
                'benutzerverifikation_fehlt' => ['flags' => PasskeyGeraet::FLAG_UP],
                default => [],
            }, $optionen);

        $this->assertRegistrierungAbgelehnt($antwort);
        $this->assertSame($vorher, $this->anzahlPasskeys(), 'Trotz Fehler wurde ein Passkey gespeichert.');

        // Challenge verbraucht: auch eine gültige Antwort auf dieselbe Challenge scheitert
        $this->assertRegistrierungAbgelehnt($this->passkeyRegistrieren($nutzer, $geraet, 'Handy', [], $optionen));
        $this->assertSame($vorher, $this->anzahlPasskeys());

        // Gegenprobe: mit neuer Challenge gelingt die Registrierung desselben Benutzers und Geräts
        $this->passkeyRegistrieren($nutzer, $geraet)->assertSuccessful();
        $this->assertSame($vorher + 1, $this->anzahlPasskeys());
        $this->assertSame(1, $this->anzahlPasskeys($nutzer->getKey()));
    }

    // AK18
    public function test_ak18_weder_antwort_noch_log_enthalten_challenge_attestation_oder_schluessel(): void
    {
        $nutzer = $this->nutzer();
        $geraet = $this->geraet();
        $this->logSammeln();
        $this->actingAs($nutzer);
        $optionen = $this->registrierungsOptionen();
        $antwort = $this->passkeyRegistrieren($nutzer, $geraet, 'Handy', ['flags' => PasskeyGeraet::FLAG_UP], $optionen);

        $this->assertRegistrierungAbgelehnt($antwort);
        $this->assertNotEmpty($this->logTexte, 'Der Fehler wird nicht geloggt (Fehlerart fehlt im Log).');
        $registrierung = $geraet->registrierung($optionen, ['flags' => PasskeyGeraet::FLAG_UP]);
        $geheimnisse = [$optionen['challenge'], ...array_filter(array_values($registrierung['response']), 'is_string'), ...$geraet->oeffentlicherSchluessel()];
        $heuhaufen = $antwort->getContent().implode("\n", $this->logTexte);
        foreach ($geheimnisse as $geheimnis) {
            $this->assertStringNotContainsString($geheimnis, $heuhaufen);
        }
    }
}
