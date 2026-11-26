<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Tests\Support\NachrichtenAbfangen;
use ITHilbert\UserAuth\Tests\Support\PasskeyGeraet;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Erfolgreiche Anmeldung per Passkey und was sie an Benutzer, Sitzung und Rechten auslöst (AK4, AK5, AK7, AK21,
 * Randfälle 3 bis 5). Fehlerarten der Assertion: PasskeyKernTest.
 */
final class PasskeyAnmeldenTest extends TestCase
{
    use NachrichtenAbfangen;
    use PasskeyTestHilfe;
    use RefreshDatabase;

    private const GEHEIM = 'GEHEIMER-INHALT';

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

    // AK4
    public function test_ak4_gueltige_assertion_meldet_den_besitzer_an_mit_neuer_sitzung_zeitstempel_und_ereignis(): void
    {
        config()->set('userauth.audit_log_enabled', true);
        config()->set('userauth.redirect_after_login', '/startseite');
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'max@firma.de']);
        $andere = $this->nutzer(['email' => 'anderer@firma.de']);
        $angemeldet = [];
        Event::listen(Login::class, function (Login $ereignis) use (&$angemeldet): void {
            $angemeldet[] = $ereignis->user->getAuthIdentifier();
        });
        $this->assertNull(DB::table('user_passkeys')->value('last_used_at'));

        $antwort = $this->passkeyAnmelden($geraet)->assertOk()->assertJson(['redirect' => '/startseite']);

        $this->assertAuthenticatedAs($nutzer);
        $this->assertNotSame($andere->getKey(), auth()->id());
        $this->assertNotSame($this->sitzungsId, session()->getId(), 'Die Sitzungs-ID bleibt gleich (Session-Fixation).');
        $this->assertNotNull(DB::table('user_passkeys')->value('last_used_at'));
        $this->assertSame([$nutzer->getKey()], $angemeldet);
        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'login')->where('user_id', $nutzer->getKey())->count());
        $this->assertNotEmpty($antwort->json('redirect'));
    }

    // AK4
    public function test_ak4_zieladresse_ist_intended_aus_dem_geschuetzten_aufruf(): void
    {
        Route::middleware(['web', 'auth'])->get('/geschuetzt', fn () => self::GEHEIM);
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'max@firma.de']);

        $this->get('/geschuetzt')->assertRedirect(route('login')); // legt das Ziel in die Sitzung
        $this->passkeyAnmelden($geraet)->assertOk()->assertJson(['redirect' => url('/geschuetzt')]);

        $this->assertAuthenticatedAs($nutzer);
    }

    // AK5
    public function test_ak5_passkey_verlangt_keinen_zwei_faktor_code_das_passwort_weiterhin(): void
    {
        config()->set('userauth.two_factor_enabled', true);
        $this->nachrichtenAbfangen();
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'max@firma.de']);

        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])->assertRedirect(route('verify.index'));
        $this->assertGuest();
        $codeMails = count($this->gesendeteNachrichten());
        $this->assertSame(1, $codeMails, 'Der Passwort-Login hat keinen Code verschickt (Aufbau greift nicht).');

        $this->passkeyAnmelden($geraet)->assertOk();

        $this->assertAuthenticatedAs($nutzer);
        $this->assertCount($codeMails, $this->gesendeteNachrichten(), 'Die Passkey-Anmeldung hat einen Zwei-Faktor-Code verschickt.');
    }

    // AK7
    public function test_ak7_geloeschter_benutzer_wird_nicht_angemeldet(): void
    {
        [$weg, $geraetWeg] = $this->nutzerMitPasskey(['email' => 'weg@firma.de']);
        [$aktiv, $geraetAktiv] = $this->nutzerMitPasskey(['email' => 'aktiv@firma.de']);
        $weg->delete();

        $this->assertPasskeyAbgelehnt($this->passkeyAnmelden($geraetWeg));
        $this->assertNull(DB::table('user_passkeys')->where('user_id', $weg->getKey())->value('last_used_at'));

        // Gegenprobe: der aktive Benutzer kommt über denselben Weg hinein
        $this->passkeyAnmelden($geraetAktiv)->assertOk();
        $this->assertAuthenticatedAs($aktiv);
    }

    // AK7
    public function test_ak7_selbstregistrierter_mit_offener_bestaetigung_wird_nicht_angemeldet(): void
    {
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'neu@firma.de']);
        DB::table('users')->where('id', $nutzer->getKey())->update(['self_registered' => 1, 'email_verified_at' => null]);

        $this->assertPasskeyAbgelehnt($this->passkeyAnmelden($geraet));
        $this->assertNull(DB::table('user_passkeys')->value('last_used_at'));

        // Gegenprobe: nach der Bestätigung der E-Mail meldet derselbe Passkey an
        DB::table('users')->where('id', $nutzer->getKey())->update(['email_verified_at' => now()]);
        $this->passkeyAnmelden($geraet)->assertOk();
        $this->assertAuthenticatedAs($nutzer);
    }

    // AK21
    public function test_ak21_rechte_rolle_und_impersonate_gelten_wie_bei_der_passwort_anmeldung(): void
    {
        $jetzt = now();
        DB::table('roles')->insert(['id' => 4, 'role' => 'zugang', 'role_display' => 'Zugang', 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        DB::table('permissions_groups')->insert(['id' => 1, 'group_name' => 'g', 'group_display' => 'G', 'is_group' => 1, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        $recht = DB::table('permissions')->insertGetId(['permission' => 'zugang', 'permission_display' => 'Zugang', 'group_id' => 1, 'crud' => 'single', 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        DB::table('role_permission')->insert(['role_id' => 4, 'permission_id' => $recht]);
        Route::middleware(['web', 'hasPermission:zugang'])->get('/_t/recht', fn () => self::GEHEIM);
        Route::middleware(['web', 'isAdmin'])->get('/_t/admin', fn () => self::GEHEIM);

        [$mitRecht, $geraetRecht] = $this->nutzerMitPasskey(['email' => 'recht@firma.de', 'role_id' => 4]);
        [$ohneRecht, $geraetOhne] = $this->nutzerMitPasskey(['email' => 'ohne@firma.de', 'role_id' => 3]);
        [$admin, $geraetAdmin] = $this->nutzerMitPasskey(['email' => 'admin@firma.de', 'role_id' => 2]);

        $this->passkeyAnmelden($geraetRecht)->assertOk();
        $this->assertTrue(auth()->user()->hasPermission('zugang'));
        $this->get('/_t/recht')->assertOk()->assertSee(self::GEHEIM);
        $this->neueSitzung();

        $this->passkeyAnmelden($geraetOhne)->assertOk();
        $this->assertFalse(auth()->user()->hasPermission('zugang'));
        $this->get('/_t/recht')->assertDontSee(self::GEHEIM);
        $this->neueSitzung();

        $this->passkeyAnmelden($geraetAdmin)->assertOk();
        $this->get('/_t/admin')->assertOk()->assertSee(self::GEHEIM);
        $this->post(route('userauth.impersonate', $ohneRecht->getKey()))->assertRedirect();
        $this->assertAuthenticatedAs($ohneRecht);
        $this->assertSame($admin->getKey(), session('impersonated_by'));
        $this->post(route('userauth.impersonate.leave'))->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    // Randfall 3
    public function test_randfall3_zwei_passkeys_eines_benutzers_funktionieren_beide_mit_eindeutiger_credential_id(): void
    {
        [$nutzer, $handy] = $this->nutzerMitPasskey(['email' => 'max@firma.de'], 'Handy');
        $laptop = $this->passkeyHinzufuegen($nutzer, 'Laptop');

        $this->assertSame(2, $this->anzahlPasskeys($nutzer->getKey()));
        $this->assertCount(2, DB::table('user_passkeys')->pluck('credential_id')->unique());
        foreach ([$handy, $laptop] as $geraet) {
            $this->passkeyAnmelden($geraet)->assertOk();
            $this->assertAuthenticatedAs($nutzer);
            $this->neueSitzung();
        }
        $this->assertCount(2, DB::table('user_passkeys')->whereNotNull('last_used_at')->get());
    }

    // Randfall 4
    public function test_randfall4_jeder_ursprung_der_liste_ist_gueltig_jeder_andere_wird_abgelehnt(): void
    {
        config()->set('userauth.passkey.rp_id', 'example.test');
        config()->set('userauth.passkey.origins', 'https://a.example.test,https://b.example.test');
        $nutzer = $this->nutzer(['email' => 'max@firma.de']);
        $geraet = new PasskeyGeraet('example.test', 'https://a.example.test');
        $this->passkeyRegistrieren($nutzer, $geraet)->assertSuccessful();
        $this->neueSitzung();

        foreach (['https://a.example.test', 'https://b.example.test'] as $ursprung) {
            $geraet->origin = $ursprung;
            $this->passkeyAnmelden($geraet)->assertOk();
            $this->assertAuthenticatedAs($nutzer);
            $this->neueSitzung();
        }

        foreach (['https://c.example.test', self::ORIGIN] as $fremd) {
            $geraet->origin = $fremd;
            $this->assertPasskeyAbgelehnt($this->passkeyAnmelden($geraet));
        }

        $geraet->origin = 'https://a.example.test';
        $this->passkeyAnmelden($geraet)->assertOk();
    }

    // Randfall 5
    public function test_randfall5_beim_echten_loeschen_werden_die_passkeys_mitgeloescht_und_melden_nicht_mehr_an(): void
    {
        [$weg, $geraetWeg] = $this->nutzerMitPasskey(['email' => 'weg@firma.de']);
        [$bleibt, $geraetBleibt] = $this->nutzerMitPasskey(['email' => 'bleibt@firma.de']);
        $this->assertSame(2, $this->anzahlPasskeys());

        $weg->forceDelete();

        $this->assertSame(0, $this->anzahlPasskeys($weg->getKey()));
        $this->assertSame(1, $this->anzahlPasskeys($bleibt->getKey()));
        $this->assertPasskeyAbgelehnt($this->passkeyAnmelden($geraetWeg));

        $this->passkeyAnmelden($geraetBleibt)->assertOk();
        $this->assertAuthenticatedAs($bleibt);
    }
}
