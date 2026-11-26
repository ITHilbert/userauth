<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\PasskeyGeraet;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Unter Impersonate sind Registrieren und Löschen von Passkeys gesperrt (AK22), Lesen der Liste bleibt möglich.
 */
final class PasskeyImpersonateTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
    }

    /** Admin übernimmt `$ziel` über die echte Route; die Sitzung gehört danach dem Ziel. */
    private function uebernehmen(User $admin, User $ziel): void
    {
        $this->neueSitzung();
        $this->actingAs($admin)->post(route('userauth.impersonate', $ziel->getKey()))->assertRedirect();
        $this->assertAuthenticatedAs($ziel);
        $this->assertSame($admin->getKey(), session('impersonated_by'), 'Der Aufbau greift nicht: keine Impersonate-Sitzung.');
    }

    private function assertAbgelehnt(TestResponse $antwort): void
    {
        $antwort->assertForbidden();
        if (str_contains((string) $antwort->headers->get('Content-Type'), 'json')) {
            $this->assertIsString($antwort->json('message'));
            $this->assertNotSame('', trim((string) $antwort->json('message')));
        }
    }

    // AK22
    public function test_ak22_registrierungsoptionen_unter_impersonate_403_ohne_challenge_ohne_impersonate_ok(): void
    {
        $admin = $this->nutzer(['email' => 'admin@firma.de', 'role_id' => 2]);
        $ziel = $this->nutzer(['email' => 'ziel@firma.de']);

        $this->uebernehmen($admin, $ziel);
        $this->assertAbgelehnt($this->postJson(route('userauth.passkey.register.options')));
        $this->assertNull(session('userauth.passkey'), 'Unter Impersonate wurde eine Challenge angelegt.');

        // nachfolgender Versuch (Impersonate beendet) mit gültiger Antwort auf eine nie ausgegebene Challenge scheitert
        $this->post(route('userauth.impersonate.leave'))->assertRedirect();
        $this->assertAuthenticatedAs($admin);
        $optionen = ['challenge' => PasskeyGeraet::b64u(random_bytes(32))];
        $this->passkeyRegistrieren($admin, $this->geraet(), 'Handy', [], $optionen)->assertStatus(422);
        $this->assertSame(0, $this->anzahlPasskeys());

        // Gegenprobe ohne Impersonate
        $this->neueSitzung();
        $this->actingAs($ziel);
        $this->assertNotEmpty($this->registrierungsOptionen()['challenge']);
    }

    // AK22
    public function test_ak22_registrieren_unter_impersonate_403_nichts_gespeichert_ohne_impersonate_ok(): void
    {
        $admin = $this->nutzer(['email' => 'admin@firma.de', 'role_id' => 2]);
        $ziel = $this->nutzer(['email' => 'ziel@firma.de']);
        $geraet = $this->geraet();

        // gültige Optionen/Challenge aus einer Sitzung ohne Impersonate, dann Impersonate in derselben Sitzung
        $this->neueSitzung();
        $this->actingAs($admin);
        $optionen = $this->registrierungsOptionen();
        $this->post(route('userauth.impersonate', $ziel->getKey()))->assertRedirect();
        $this->assertSame($admin->getKey(), session('impersonated_by'), 'Der Aufbau greift nicht: keine Impersonate-Sitzung.');

        $antwort = $this->postJson(route('userauth.passkey.register'), ['credential' => $geraet->registrierung($optionen), 'name' => 'Handy']);
        $this->assertAbgelehnt($antwort);
        $this->assertSame(0, $this->anzahlPasskeys(), 'Unter Impersonate wurde ein Passkey gespeichert.');

        // Gegenprobe: dieselbe Antwort ohne Impersonate gelingt
        $this->post(route('userauth.impersonate.leave'))->assertRedirect();
        $this->assertAuthenticatedAs($admin);
        $this->postJson(route('userauth.passkey.register'), ['credential' => $geraet->registrierung($this->registrierungsOptionen()), 'name' => 'Handy'])->assertSuccessful();
        $this->assertSame(1, $this->anzahlPasskeys($admin->getKey()));
    }

    // AK22
    public function test_ak22_loeschen_unter_impersonate_403_nichts_geloescht_ohne_impersonate_ok(): void
    {
        $admin = $this->nutzer(['email' => 'admin@firma.de', 'role_id' => 2]);
        [$ziel] = $this->nutzerMitPasskey(['email' => 'ziel@firma.de'], 'Handy');
        $id = DB::table('user_passkeys')->where('user_id', $ziel->getKey())->value('id');

        $this->uebernehmen($admin, $ziel);
        $this->delete(route('userauth.passkey.destroy', $id))->assertForbidden();
        $this->assertSame(1, $this->anzahlPasskeys($ziel->getKey()), 'Unter Impersonate wurde ein Passkey gelöscht.');

        // Gegenprobe ohne Impersonate
        $this->neueSitzung();
        $this->actingAs($ziel)->delete(route('userauth.passkey.destroy', $id))->assertRedirect(route('userauth.passkey.index'));
        $this->assertSame(0, $this->anzahlPasskeys($ziel->getKey()));
    }

    // AK22
    public function test_ak22_liste_bleibt_unter_impersonate_lesbar(): void
    {
        $admin = $this->nutzer(['email' => 'admin@firma.de', 'role_id' => 2]);
        [$ziel] = $this->nutzerMitPasskey(['email' => 'ziel@firma.de'], 'Handy');

        $this->uebernehmen($admin, $ziel);
        $this->get(route('userauth.passkey.index'))->assertOk();
    }
}
