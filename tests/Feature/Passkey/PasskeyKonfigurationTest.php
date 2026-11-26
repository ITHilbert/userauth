<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Paketdateien der Passkey-Anmeldung (AK19): composer.json, config.php, README, CHANGELOG und die Migration.
 */
final class PasskeyKonfigurationTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
    }

    private function paket(): string
    {
        return dirname(__DIR__, 3);
    }

    /** README-Abschnitt ab der Überschrift bis zur nächsten Überschrift gleicher oder höherer Ebene. */
    private function readmeAbschnitt(string $titel): string
    {
        $readme = (string) file_get_contents($this->paket().'/README.md');
        $this->assertSame(1, preg_match('/^(#{1,6}) [^\n]*'.preg_quote($titel, '/').'[^\n]*$/mu', $readme, $treffer, PREG_OFFSET_CAPTURE), "Abschnitt „{$titel}“ fehlt in der README.");
        $rest = substr($readme, $treffer[0][1] + strlen($treffer[0][0]));
        $ende = preg_match('/^#{1,'.strlen($treffer[1][0]).'} /m', $rest, $naechste, PREG_OFFSET_CAPTURE) === 1 ? $naechste[0][1] : strlen($rest);

        return substr($rest, 0, $ende);
    }

    // AK19
    public function test_ak19_composer_json_verlangt_die_webauthn_bibliothek(): void
    {
        $composer = json_decode((string) file_get_contents($this->paket().'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('^5.3', $composer['require']['web-auth/webauthn-lib'] ?? null);
    }

    // AK19
    public function test_ak19_config_enthaelt_den_block_passkey_und_passwort_login_mit_standardwerten(): void
    {
        $standard = require $this->paket().'/src/Config/config.php';

        $this->assertIsArray($standard['passkey'] ?? null, 'Der Block passkey fehlt in config.php.');
        $this->assertEqualsCanonicalizing(['enabled', 'rp_id', 'rp_name', 'origins'], array_keys($standard['passkey']));
        $this->assertFalse($standard['passkey']['enabled']);
        foreach (['rp_id', 'rp_name', 'origins'] as $leer) {
            $this->assertContains($standard['passkey'][$leer], ['', null, []], "Standard von {$leer} ist nicht leer.");
        }
        $this->assertSame('immer', $standard['passwort_login'] ?? null);

        $quelltext = (string) file_get_contents($this->paket().'/src/Config/config.php');
        foreach (['USERAUTH_PASSKEY_ENABLED', 'USERAUTH_PASSKEY_RP_ID', 'USERAUTH_PASSKEY_RP_NAME', 'USERAUTH_PASSKEY_ORIGINS', 'USERAUTH_PASSWORT_LOGIN', 'ohne_passkey', 'nie'] as $stichwort) {
            $this->assertStringContainsString($stichwort, $quelltext, "config.php nennt {$stichwort} nicht.");
        }
    }

    // AK19
    public function test_ak19_readme_hat_den_abschnitt_anmeldung_mit_passkey(): void
    {
        $abschnitt = $this->readmeAbschnitt('Anmeldung mit Passkey');

        foreach (['USERAUTH_PASSKEY_ENABLED', 'USERAUTH_PASSKEY_RP_ID', 'USERAUTH_PASSKEY_RP_NAME', 'USERAUTH_PASSKEY_ORIGINS', 'USERAUTH_PASSWORT_LOGIN',
            'Passkey und Passwort', 'nur Passkey', 'nur OIDC', 'ohne_passkey', 'HTTPS', 'localhost', 'rp_id',
            'userauth:passkeys-zuruecksetzen', 'Zweitgerät'] as $stichwort) {
            $this->assertStringContainsString($stichwort, $abschnitt, "README-Abschnitt nennt {$stichwort} nicht.");
        }
    }

    // AK19
    public function test_ak19_changelog_nennt_die_aenderung_im_neuesten_eintrag(): void
    {
        $neuesterEintrag = substr((string) file_get_contents($this->paket().'/CHANGELOG.md'), 0, 2500);

        $this->assertMatchesRegularExpression('/passkey/i', $neuesterEintrag);
    }

    // AK19
    public function test_ak19_migration_user_passkeys_laedt_mit_den_anderen_und_legt_die_tabelle_an(): void
    {
        $this->assertTrue(Schema::hasTable('user_passkeys'));
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'name', 'credential_id', 'credential_source', 'last_used_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('user_passkeys'),
        );

        // credential_id ist eindeutig, user_id verlangt einen Benutzer
        $benutzer = $this->nutzer();
        $zeile = ['user_id' => $benutzer->getKey(), 'name' => 'a', 'credential_id' => 'abc', 'credential_source' => '{}', 'created_at' => now(), 'updated_at' => now()];
        DB::table('user_passkeys')->insert($zeile);
        $this->assertSame(1, DB::table('user_passkeys')->count(), 'Aufbau: die erste Zeile lässt sich speichern.');

        try {
            DB::table('user_passkeys')->insert($zeile);
            $this->fail('credential_id ist nicht eindeutig.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('user_passkeys')->count());
        }
        try {
            DB::table('user_passkeys')->insert([...$zeile, 'user_id' => 99999, 'credential_id' => 'xyz']);
            $this->fail('user_id verweist nicht auf einen Benutzer.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('user_passkeys')->count());
        }
    }
}
