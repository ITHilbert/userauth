<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Begrenzung der Bestätigungsrouten mit Passkey und Fortschreiben des Passkeys (userauth passkey-bestaetigung, AK13).
 */
final class PasskeyBestaetigungLimitTest extends TestCase
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

    // AK13
    public function test_ak13_optionen_31ster_aufruf_innerhalb_einer_minute_429_der_30ste_noch_nicht(): void
    {
        $this->passkeyAutoBestaetigen = true;
        [$nutzer] = $this->nutzerMitPasskey();
        $this->actingAs($nutzer);

        for ($i = 1; $i <= 30; $i++) {
            $this->postJson(route('userauth.passkey.bestaetigen.optionen'))->assertOk();
        }
        $this->postJson(route('userauth.passkey.bestaetigen.optionen'))->assertStatus(429);
    }

    // AK13
    public function test_ak13_assertion_31ster_aufruf_innerhalb_einer_minute_429_der_30ste_noch_nicht(): void
    {
        $this->passkeyAutoBestaetigen = true;
        [$nutzer] = $this->nutzerMitPasskey();
        $this->actingAs($nutzer);

        for ($i = 1; $i <= 30; $i++) {
            $this->postJson(route('userauth.passkey.bestaetigen.assertion'), [])->assertStatus(422);
        }
        $this->postJson(route('userauth.passkey.bestaetigen.assertion'), [])->assertStatus(429);
    }

    // AK13
    public function test_ak13_gueltige_assertion_schreibt_signaturzaehler_und_zuletzt_benutzt_fort(): void
    {
        $this->passkeyAutoBestaetigen = true;
        [$nutzer, $geraet] = $this->nutzerMitPasskey();
        $this->passkeyAutoBestaetigen = false;
        $this->actingAs($nutzer);
        $vorher = DB::table('user_passkeys')->first();
        $this->assertNull($vorher->last_used_at, 'Aufbau: der Passkey wurde noch nie benutzt.');
        $zaehlerVorher = $this->zaehlerAusQuelle($vorher->credential_source);

        $optionen = $this->postJson(route('userauth.passkey.bestaetigen.optionen'))->assertOk()->json();
        $this->postJson(route('userauth.passkey.bestaetigen.assertion'), $geraet->anmeldung($optionen))->assertNoContent();

        $nachher = DB::table('user_passkeys')->first();
        $this->assertNotNull($nachher->last_used_at);
        $this->assertGreaterThan($zaehlerVorher, $this->zaehlerAusQuelle($nachher->credential_source));
    }

    private function zaehlerAusQuelle(string $quelle): int
    {
        $daten = json_decode($quelle, true, flags: JSON_THROW_ON_ERROR);
        $zaehler = $daten['counter'] ?? $daten['signatureCounter'] ?? null;
        $this->assertIsInt($zaehler, 'Kein Signaturzähler in credential_source.');

        return $zaehler;
    }
}
