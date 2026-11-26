<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\PasskeyGeraet;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Bestätigung vor „Passkey hinzufügen“ (userauth passkey-bestaetigung, #2225): Sperre der Registrierungsrouten,
 * Bestätigung per Passwort, Frist, Drosselung, Randfälle, Impersonate und Löschen ohne Bestätigung.
 * Der Helfer `PasskeyTestHilfe` bestätigt hier nicht von selbst. Das aktuelle Passwort der Factory-Benutzer ist `password`.
 */
final class PasskeyBestaetigungTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    private const FRIST_START = '2031-03-14 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
        $this->passkeyAutoBestaetigen = false;
    }

    protected function tearDown(): void
    {
        $this->passkeyAbbauen();

        parent::tearDown();
    }

    private function anmelden(): User
    {
        $nutzer = $this->nutzer();
        $this->actingAs($nutzer);

        return $nutzer;
    }

    private function bestaetigen(string $passwort = 'password'): TestResponse
    {
        return $this->postJson(route('userauth.passkey.bestaetigen'), ['aktuelles_passwort' => $passwort]);
    }

    private function assertErforderlich(TestResponse $antwort): void
    {
        $antwort->assertForbidden();
        $this->assertTrue($antwort->json('bestaetigung_erforderlich'), 'Die Antwort trägt `bestaetigung_erforderlich: true` nicht.');
        $this->assertIsString($antwort->json('message'));
        $this->assertNotSame('', trim((string) $antwort->json('message')));
    }

    /** Beide Registrierungsrouten verlangen die Bestätigung; nichts entsteht in Sitzung oder Datenbank. */
    private function assertRegistrierungGesperrt(): void
    {
        $this->assertErforderlich($this->postJson(route('userauth.passkey.register.options')));
        $this->assertErforderlich($this->postJson(route('userauth.passkey.register'), [
            'credential' => $this->geraet()->registrierung(['challenge' => PasskeyGeraet::b64u(random_bytes(32)), 'user' => ['id' => PasskeyGeraet::b64u(random_bytes(16))]]),
            'name' => 'Handy',
        ]));
        $this->assertSame(0, $this->anzahlPasskeys());
    }

    /** Beide Registrierungsrouten verhalten sich wie vor der Änderung: Optionen, dann Speichern. */
    private function assertRegistrierungMoeglich(User $nutzer): void
    {
        $vorher = $this->anzahlPasskeys($nutzer->getKey());
        $this->passkeyRegistrieren($nutzer, $this->geraet(), 'Neu')->assertSuccessful();
        $this->assertSame($vorher + 1, $this->anzahlPasskeys($nutzer->getKey()));
    }

    // AK1
    public function test_ak1_ohne_bestaetigung_403_mit_marker_ohne_challenge_und_ohne_passkey_in_beiden_sprachen(): void
    {
        $nutzer = $this->anmelden();
        $this->assertRegistrierungGesperrt();
        $this->assertSame([], array_filter(array_keys(Arr::dot(session()->all())), fn (string $schluessel): bool => str_starts_with($schluessel, 'userauth.passkey')), 'Trotz 403 steht ein Passkey-Wert in der Sitzung.');

        $de = $this->postJson(route('userauth.passkey.register.options'))->json('message');
        app()->setLocale('en');
        $en = $this->postJson(route('userauth.passkey.register.options'))->json('message');
        $this->assertNotSame($de, $en, 'Die 403-Meldung ist nicht übersetzt.');

        // Gegenprobe: mit Bestätigung gibt es dieselben Routen frei (der Aufbau greift)
        $this->bestaetigen()->assertNoContent();
        $this->assertRegistrierungMoeglich($nutzer);
    }

    // AK2
    public function test_ak2_richtiges_passwort_204_und_beide_registrierungsrouten_laufen_wie_zuvor(): void
    {
        $nutzer = $this->anmelden();
        $this->assertErforderlich($this->postJson(route('userauth.passkey.register.options')));

        $antwort = $this->bestaetigen()->assertNoContent();
        $this->assertSame('', $antwort->getContent());

        $this->assertNotEmpty($this->registrierungsOptionen()['challenge']);
        $this->assertRegistrierungMoeglich($nutzer);
    }

    // AK3
    public function test_ak3_bestaetigung_gilt_genau_bis_zur_frist_danach_wie_ak1(): void
    {
        Carbon::setTestNow(self::FRIST_START);
        $nutzer = $this->anmelden();
        $this->bestaetigen()->assertNoContent();

        Carbon::setTestNow(Carbon::parse(self::FRIST_START)->addMinutes(10));
        $this->assertNotEmpty($this->registrierungsOptionen()['challenge'], 'Genau nach 10 Minuten gilt die Bestätigung noch.');

        Carbon::setTestNow(Carbon::parse(self::FRIST_START)->addMinutes(10)->addSecond());
        $this->assertRegistrierungGesperrt();

        // Gegenprobe: eine neue Bestätigung öffnet die Frist wieder
        $this->bestaetigen()->assertNoContent();
        $this->assertRegistrierungMoeglich($nutzer);
    }

    // AK3 (die Frist folgt der Konfiguration, nicht einem festen Wert)
    public function test_ak3_frist_folgt_bestaetigung_minuten_aus_der_konfiguration(): void
    {
        config()->set('userauth.passkey.bestaetigung_minuten', 3);
        Carbon::setTestNow(self::FRIST_START);
        $this->anmelden();
        $this->bestaetigen()->assertNoContent();

        Carbon::setTestNow(Carbon::parse(self::FRIST_START)->addMinutes(3));
        $this->assertNotEmpty($this->registrierungsOptionen()['challenge']);
        Carbon::setTestNow(Carbon::parse(self::FRIST_START)->addMinutes(3)->addSecond());
        $this->assertErforderlich($this->postJson(route('userauth.passkey.register.options')));
    }

    // AK3 (Challenge ist noch gültig, die Frist der Bestätigung abgelaufen: die Sperre greift vor der Challenge-Prüfung)
    public function test_ak3_abgelaufene_bestaetigung_sperrt_auch_mit_gueltiger_challenge_und_gueltiger_antwort(): void
    {
        Carbon::setTestNow(self::FRIST_START);
        config()->set('userauth.passkey.bestaetigung_minuten', 1);
        $nutzer = $this->anmelden();
        $geraet = $this->geraet();
        $this->bestaetigen()->assertNoContent();
        $optionen = $this->registrierungsOptionen();
        $antwort = $geraet->registrierung($optionen);

        Carbon::setTestNow(Carbon::parse(self::FRIST_START)->addMinutes(2));
        $this->assertErforderlich($this->postJson(route('userauth.passkey.register'), ['credential' => $antwort, 'name' => 'Handy']));
        $this->assertSame(0, $this->anzahlPasskeys());
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function ungueltigePasswoerter(): array
    {
        return [
            'falsch' => [['aktuelles_passwort' => 'falsches-Passwort-1']],
            'leer' => [['aktuelles_passwort' => '']],
            'fehlt' => [[]],
        ];
    }

    // AK4
    #[DataProvider('ungueltigePasswoerter')]
    public function test_ak4_falsches_leeres_oder_fehlendes_passwort_422_ohne_bestaetigung(array $daten): void
    {
        $nutzer = $this->anmelden();

        $this->postJson(route('userauth.passkey.bestaetigen'), $daten)->assertStatus(422);
        $this->assertRegistrierungGesperrt();

        // Gegenprobe: das richtige Passwort bestätigt in derselben Sitzung
        $this->bestaetigen()->assertNoContent();
        $this->assertRegistrierungMoeglich($nutzer);
    }

    // AK4
    public function test_ak4_sechster_versuch_innerhalb_einer_minute_429_auch_mit_richtigem_passwort(): void
    {
        $this->anmelden();

        for ($versuch = 1; $versuch <= 5; $versuch++) {
            $this->bestaetigen('falsch-'.$versuch)->assertStatus(422);
        }
        $this->bestaetigen('password')->assertStatus(429);
        $this->assertRegistrierungGesperrt();
    }

    // AK4
    public function test_ak4_passwort_steht_weder_in_antwort_noch_im_log(): void
    {
        $this->anmelden();
        $this->logSammeln();
        $falsch = 'Geheim-Passwort-4711!';

        $antwort = $this->bestaetigen($falsch)->assertStatus(422);
        $richtig = $this->bestaetigen('password')->assertNoContent();

        $heuhaufen = $antwort->getContent().$richtig->getContent().implode("\n", $this->logTexte);
        $this->assertStringNotContainsString($falsch, $heuhaufen);
    }

    // AK7
    public function test_ak7_passkeys_aus_alle_drei_routen_404_passkeys_an_gast_wird_umgeleitet(): void
    {
        $namen = ['userauth.passkey.bestaetigen', 'userauth.passkey.bestaetigen.optionen', 'userauth.passkey.bestaetigen.assertion'];

        // Gast bei aktiven Passkeys: `auth` leitet auf die Anmeldung um
        foreach ($namen as $name) {
            $this->post(route($name))->assertRedirect(route('login'));
        }

        $this->anmelden();
        config()->set('userauth.passkey.enabled', false);
        foreach ($namen as $name) {
            $this->postJson(route($name), ['aktuelles_passwort' => 'password'])->assertNotFound();
        }

        // Gegenprobe: eingeschaltet ist die Passwort-Route erreichbar
        config()->set('userauth.passkey.enabled', true);
        $this->bestaetigen()->assertNoContent();
    }

    /** Admin übernimmt `$ziel` über die echte Route. */
    private function uebernehmen(User $admin, User $ziel): void
    {
        $this->actingAs($admin)->post(route('userauth.impersonate', $ziel->getKey()))->assertRedirect();
        $this->assertAuthenticatedAs($ziel);
        $this->assertSame($admin->getKey(), session('impersonated_by'), 'Der Aufbau greift nicht: keine Impersonate-Sitzung.');
    }

    // AK9
    public function test_ak9_unter_impersonate_403_fuer_alle_drei_routen_und_eine_fruehere_bestaetigung_gilt_danach_nicht(): void
    {
        $admin = $this->nutzer(['email' => 'admin@firma.de', 'role_id' => 2]);
        $ziel = $this->nutzer(['email' => 'ziel@firma.de']);
        $this->actingAs($admin);
        $this->bestaetigen()->assertNoContent();
        $this->assertNotEmpty($this->registrierungsOptionen()['challenge'], 'Der Aufbau greift nicht: Admin ist bestätigt.');

        $this->uebernehmen($admin, $ziel);
        $this->bestaetigen()->assertForbidden();
        $this->postJson(route('userauth.passkey.bestaetigen.optionen'))->assertForbidden();
        $this->postJson(route('userauth.passkey.bestaetigen.assertion'), ['id' => 'x'])->assertForbidden();
        $this->postJson(route('userauth.passkey.register.options'))->assertForbidden();

        // nach dem Ende der Übernahme (neue Anmeldung des Admins) ist die frühere Bestätigung weg
        $this->post(route('userauth.impersonate.leave'))->assertRedirect();
        $this->assertAuthenticatedAs($admin);
        $this->assertErforderlich($this->postJson(route('userauth.passkey.register.options')));
    }

    // Randfall 1
    public function test_randfall1_benutzer_ohne_nutzbares_passwort_und_ohne_passkey_kann_nichts_bestaetigen(): void
    {
        $nutzer = $this->nutzer(['password' => Hash::make(Str::random(40))]);
        $this->actingAs($nutzer);

        foreach (['password', '', Str::random(12)] as $raten) {
            $this->bestaetigen($raten)->assertStatus(422);
        }
        $this->assertErforderlich($this->postJson(route('userauth.passkey.register.options')));
        $this->assertSame(0, $this->anzahlPasskeys());
    }

    // Randfall 2
    public function test_randfall2_bestaetigung_gilt_nicht_fuer_einen_anderen_benutzer_in_derselben_sitzung(): void
    {
        $erster = $this->nutzer(['email' => 'erster@firma.de']);
        $zweiter = $this->nutzer(['email' => 'zweiter@firma.de']);
        $this->actingAs($erster);
        $this->bestaetigen()->assertNoContent();
        $this->assertNotEmpty($this->registrierungsOptionen()['challenge'], 'Der Aufbau greift nicht: erster Benutzer ist bestätigt.');

        $this->actingAs($zweiter);
        $this->assertErforderlich($this->postJson(route('userauth.passkey.register.options')));
        $this->assertSame(0, $this->anzahlPasskeys($zweiter->getKey()));
    }

    // Randfall 3
    public function test_randfall3_zwei_registrierungen_innerhalb_der_frist_sind_beide_erlaubt(): void
    {
        $nutzer = $this->anmelden();
        $this->bestaetigen()->assertNoContent();

        $this->passkeyRegistrieren($nutzer, $this->geraet(), 'Erstes')->assertSuccessful();
        $this->passkeyRegistrieren($nutzer, $this->geraet(), 'Zweites')->assertSuccessful();

        $this->assertSame(2, $this->anzahlPasskeys($nutzer->getKey()));
    }

    // AK1 (Abmelden löscht die Bestätigung, Spec „Hintergrund“)
    public function test_ak1_nach_dem_abmelden_und_erneuter_sitzung_ist_die_bestaetigung_weg(): void
    {
        $nutzer = $this->anmelden();
        $this->bestaetigen()->assertNoContent();
        $this->assertNotEmpty($this->registrierungsOptionen()['challenge'], 'Der Aufbau greift nicht: Benutzer ist bestätigt.');

        $this->post(route('logout'))->assertRedirect();
        $this->actingAs($nutzer);
        $this->assertErforderlich($this->postJson(route('userauth.passkey.register.options')));
    }

    // AK11
    public function test_ak11_loeschen_bleibt_ohne_bestaetigung_moeglich(): void
    {
        $this->passkeyAutoBestaetigen = true;
        [$nutzer] = $this->nutzerMitPasskey();
        $this->passkeyAutoBestaetigen = false;
        $id = DB::table('user_passkeys')->where('user_id', $nutzer->getKey())->value('id');
        $this->assertSame(1, $this->anzahlPasskeys($nutzer->getKey()), 'Der Aufbau greift nicht: Passkey fehlt.');

        $this->actingAs($nutzer);
        $this->assertErforderlich($this->postJson(route('userauth.passkey.register.options')));
        $this->delete(route('userauth.passkey.destroy', $id))->assertRedirect(route('userauth.passkey.index'));

        $this->assertSame(0, $this->anzahlPasskeys($nutzer->getKey()));
    }
}
