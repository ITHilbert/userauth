<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use DOMDocument;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Passkeys zurücksetzen durch einen Administrator (AK15) und per Artisan-Befehl (AK16).
 *
 * Vorgaben an die Bearbeiten-Seite des Benutzers: die Anzahl steht in einem Element `data-testid="passkey-anzahl"`;
 * der Knopf ist ein Formular mit der Adresse der Route `user.passkeys.reset`. Das Audit-Ereignis heißt `…passkey…`.
 */
final class PasskeyZuruecksetzenTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    private User $admin;

    private User $ziel;

    private User $unbeteiligt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
        $this->userEditRechtAnlegen();
        $this->admin = $this->nutzer(['email' => 'admin@firma.de', 'role_id' => 2]);
        [$this->ziel] = $this->nutzerMitPasskey(['email' => 'ziel@firma.de'], 'Handy');
        $this->passkeyHinzufuegen($this->ziel, 'Laptop');
        [$this->unbeteiligt] = $this->nutzerMitPasskey(['email' => 'anderer@firma.de'], 'Fremd');
    }

    private function zuruecksetzen(): TestResponse
    {
        return $this->post(route('user.passkeys.reset', $this->ziel->getKey()));
    }

    // AK15
    public function test_ak15_administrator_loescht_alle_passkeys_des_benutzers_und_das_audit_log_vermerkt_es(): void
    {
        config()->set('userauth.audit_log_enabled', true);
        $this->assertSame('/users/passkeys/reset/7', route('user.passkeys.reset', 7, false));
        $vorher = DB::table('user_auth_audit_logs')->count();

        $this->actingAs($this->admin);
        $this->zuruecksetzen()->assertRedirect();

        $this->assertSame(0, $this->anzahlPasskeys($this->ziel->getKey()));
        $this->assertSame(1, $this->anzahlPasskeys($this->unbeteiligt->getKey()), 'Passkeys eines anderen Benutzers wurden gelöscht.');
        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'like', '%passkey%')->count());
        $this->assertSame($vorher + 1, DB::table('user_auth_audit_logs')->count());
    }

    // AK15
    public function test_ak15_ohne_recht_user_edit_antwortet_403_und_loescht_nichts(): void
    {
        config()->set('userauth.redirect_on_no_permission', '403');

        $this->actingAs($this->nutzer(['email' => 'normal@firma.de', 'role_id' => 3]));
        $this->zuruecksetzen()->assertForbidden();
        $this->assertSame(2, $this->anzahlPasskeys($this->ziel->getKey()));

        // Gegenprobe: dieselbe Anfrage mit Recht löscht
        $this->neueSitzung();
        $this->actingAs($this->admin);
        $this->zuruecksetzen()->assertRedirect();
        $this->assertSame(0, $this->anzahlPasskeys($this->ziel->getKey()));
    }

    /** @return array<string, array{0: bool, 1: bool, 2: bool}> aktiv, Passkeys vorhanden, Knopf erwartet */
    public static function zustaende(): array
    {
        return ['aktiv mit Passkeys' => [true, true, true], 'aktiv ohne Passkeys' => [true, false, false], 'aus mit Passkeys' => [false, true, false]];
    }

    // AK15
    #[DataProvider('zustaende')]
    public function test_ak15_bearbeiten_seite_zeigt_anzahl_und_knopf_nur_bei_aktivem_passkey_und_mindestens_einem(bool $aktiv, bool $vorhanden, bool $knopf): void
    {
        if (! $vorhanden) {
            DB::table('user_passkeys')->where('user_id', $this->ziel->getKey())->delete();
        }
        config()->set('userauth.passkey.enabled', $aktiv);
        $this->actingAs($this->admin);

        $html = (string) $this->get(route('user.edit', $this->ziel->getKey()))->assertOk()->getContent();

        $this->assertSame($knopf, str_contains($html, route('user.passkeys.reset', $this->ziel->getKey())));
        if ($aktiv && $vorhanden) {
            $dom = new DOMDocument;
            @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            $anzahl = (new DOMXPath($dom))->query('//*[@data-testid="passkey-anzahl"]');
            $this->assertSame(1, $anzahl->length, 'Die Anzahl der Passkeys fehlt.');
            $this->assertStringContainsString('2', $anzahl->item(0)->textContent);
        }
    }

    // AK16
    public function test_ak16_befehl_loescht_alle_passkeys_des_aktiven_benutzers_und_meldet_die_anzahl(): void
    {
        $this->artisan('userauth:passkeys-zuruecksetzen', ['email' => 'ziel@firma.de'])
            ->expectsOutputToContain('2')
            ->assertExitCode(0);

        $this->assertSame(0, $this->anzahlPasskeys($this->ziel->getKey()));
        $this->assertSame(1, $this->anzahlPasskeys($this->unbeteiligt->getKey()));
    }

    // AK16
    public function test_ak16_unbekannte_email_ist_ein_fehler_und_loescht_nichts(): void
    {
        $this->artisan('userauth:passkeys-zuruecksetzen', ['email' => 'niemand@firma.de'])->assertFailed();
        $this->assertSame(3, $this->anzahlPasskeys());

        // Gegenprobe: mit bekannter E-Mail läuft derselbe Befehl durch
        $this->artisan('userauth:passkeys-zuruecksetzen', ['email' => 'ziel@firma.de'])->assertSuccessful();
        $this->assertSame(1, $this->anzahlPasskeys());
    }

    // AK16
    public function test_ak16_mehrdeutige_email_ist_ein_fehler_und_loescht_nichts(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->dropUnique('users_email_unique');
            $t->index('email');
        });
        $zweiter = $this->nutzer(['email' => 'ziel@firma.de']);
        $this->passkeyHinzufuegen($zweiter, 'Zweites Konto');
        $this->assertSame(4, $this->anzahlPasskeys());

        $this->artisan('userauth:passkeys-zuruecksetzen', ['email' => 'ziel@firma.de'])->assertFailed();
        $this->assertSame(4, $this->anzahlPasskeys());

        // Gegenprobe: gehört die zweite Zeile einem gelöschten Benutzer, ist der aktive Benutzer eindeutig
        $zweiter->delete();
        $this->artisan('userauth:passkeys-zuruecksetzen', ['email' => 'ziel@firma.de'])->assertSuccessful();
        $this->assertSame(0, $this->anzahlPasskeys($this->ziel->getKey()));
        $this->assertSame(1, $this->anzahlPasskeys($this->unbeteiligt->getKey()));
    }
}
