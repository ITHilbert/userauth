<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use ITHilbert\UserAuth\Tests\Support\NachrichtenAbfangen;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Wirkung von `userauth.passwort_login` auf `POST /login` und auf „Passwort vergessen“ (AK12, AK13, AK14).
 * Das Feld der Kennung ist `email` (Standard von `userauth.anmeldung_per`).
 */
final class PasswortLoginModusTest extends TestCase
{
    use NachrichtenAbfangen;
    use PasskeyTestHilfe;
    use RefreshDatabase;

    private const NEUES_PASSWORT = 'Neues-Passwort-2026!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
        config()->set('userauth.password_policy.enabled', false);
    }

    /** Meldung zur Kennung nach einer Anmeldung mit falschem Passwort. */
    private function meldungBeiFalschemPasswort(string $email): string
    {
        $this->post('/login', ['email' => $email, 'password' => 'falsch'])->assertSessionHasErrors('email');
        $meldung = (string) session('errors')->first('email');
        $this->assertNotSame('', $meldung);
        $this->assertGuest();

        return $meldung;
    }

    /** Richtige Zugangsdaten, aber abgelehnt: dieselbe Meldung am Feld der Kennung wie bei falschem Passwort. */
    private function assertAbgelehntWieFalschesPasswort(string $email, string $passwort, string $erwarteteMeldung): void
    {
        $this->post('/login', ['email' => $email, 'password' => $passwort])->assertSessionHasErrors('email');

        $this->assertSame($erwarteteMeldung, (string) session('errors')->first('email'));
        $this->assertGuest();
    }

    // AK12
    public function test_ak12_ohne_passkey_modus_sperrt_das_passwort_nur_fuer_benutzer_mit_passkey(): void
    {
        [$mitPasskey, $geraet] = $this->nutzerMitPasskey(['email' => 'max@firma.de']);
        $ohne = $this->nutzer(['email' => 'ohne@firma.de']);
        config()->set('userauth.passwort_login', 'ohne_passkey');

        $standard = $this->meldungBeiFalschemPasswort('max@firma.de');
        $this->assertAbgelehntWieFalschesPasswort('max@firma.de', 'password', $standard);

        // Benutzer ohne Passkey melden sich weiter mit Passwort an
        $this->post('/login', ['email' => 'ohne@firma.de', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($ohne);
        $this->neueSitzung();

        // Gegenprobe im selben Test: derselbe Benutzer ohne Passkey wird angemeldet
        DB::table('user_passkeys')->where('user_id', $mitPasskey->getKey())->delete();
        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($mitPasskey);
    }

    /** @return array<string, array{0: string}> */
    public static function wirkungsloseModi(): array
    {
        return ['immer' => ['immer'], 'unbekannter Wert' => ['manchmal'], 'leer' => ['']];
    }

    // AK13
    #[DataProvider('wirkungsloseModi')]
    public function test_ak13_immer_und_jeder_unbekannte_wert_verhalten_sich_wie_zuvor(string $modus): void
    {
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'max@firma.de']);
        config()->set('userauth.passwort_login', $modus);

        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($nutzer);
    }

    // AK13
    public function test_ak13_nie_sperrt_das_passwort_fuer_alle_auch_ohne_passkey_und_bei_ausgeschaltetem_passkey(): void
    {
        $ohne = $this->nutzer(['email' => 'ohne@firma.de']);
        [$mit, $geraet] = $this->nutzerMitPasskey(['email' => 'mit@firma.de']);
        config()->set('userauth.passwort_login', 'nie');

        foreach (['ohne@firma.de', 'mit@firma.de'] as $email) {
            $standard = $this->meldungBeiFalschemPasswort($email);
            $this->assertAbgelehntWieFalschesPasswort($email, 'password', $standard);
            $this->neueSitzung();
        }

        // „nur OIDC“: Passkey aus, Passwort bleibt gesperrt
        config()->set('userauth.passkey.enabled', false);
        $this->assertAbgelehntWieFalschesPasswort('ohne@firma.de', 'password', $this->meldungBeiFalschemPasswort('ohne@firma.de'));
        $this->neueSitzung();

        // Gegenprobe: mit „immer“ meldet dieselbe Anfrage an
        config()->set('userauth.passwort_login', 'immer');
        $this->post('/login', ['email' => 'ohne@firma.de', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($ohne);
    }

    /** @return array<string, array{0: string}> */
    public static function sperrModi(): array
    {
        return ['ohne_passkey' => ['ohne_passkey'], 'nie' => ['nie']];
    }

    // AK14
    #[DataProvider('sperrModi')]
    public function test_ak14_passwort_vergessen_setzt_das_passwort_meldet_nicht_an_und_die_sperre_gilt_danach(string $modus): void
    {
        [$nutzer, $geraet] = $this->nutzerMitPasskey(['email' => 'max@firma.de']);
        config()->set('userauth.passwort_login', $modus);
        $this->nachrichtenAbfangen();

        $this->post(route('password.sendtocken'), ['email' => 'max@firma.de'])->assertRedirect();
        $nachrichten = $this->nachrichtenAn('max@firma.de');
        $this->assertNotEmpty($nachrichten, 'Es wurde keine Reset-Mail versendet.');
        $link = (string) $this->linkAusNachricht(end($nachrichten));
        $this->assertSame(1, preg_match('~password/editwithtoken/([^/]+)~', (string) parse_url($link, PHP_URL_PATH), $treffer));

        $this->post(route('password.updatewithtoken'), [
            'email' => 'max@firma.de',
            'pwtoken' => urldecode($treffer[1]),
            'password' => self::NEUES_PASSWORT,
            'password_confirmation' => self::NEUES_PASSWORT,
        ])->assertRedirect();

        $this->assertGuest();
        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, (string) DB::table('users')->where('id', $nutzer->getKey())->value('password')), 'Das Passwort wurde nicht gesetzt.');

        // danach gilt die Sperre; Gegenprobe: ohne Sperre meldet das neue Passwort an
        $this->post('/login', ['email' => 'max@firma.de', 'password' => self::NEUES_PASSWORT])->assertSessionHasErrors('email');
        $this->assertGuest();
        config()->set('userauth.passwort_login', 'immer');
        $this->post('/login', ['email' => 'max@firma.de', 'password' => self::NEUES_PASSWORT])->assertRedirect('/');
        $this->assertAuthenticatedAs($nutzer);
    }
}
