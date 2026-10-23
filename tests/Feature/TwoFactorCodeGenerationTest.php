<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\NachrichtenAbfangen;
use ITHilbert\UserAuth\Tests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Erzeugung und Prüfung des 2FA-Codes (AK18, userauth-haertung).
 *
 * Ersetzt die früheren Tests der toten Trait-Kopie `TwoFactorAuthenticatable` (Code war dort `[1-9]\d{5}`, direkt in
 * der Datenbank). Geprüft wird jetzt das Verhalten über den Anmeldeweg; die Umsetzung (Klasse, Ablage) ist frei.
 */
final class TwoFactorCodeGenerationTest extends TestCase
{
    use NachrichtenAbfangen;
    use RefreshDatabase;

    private const ANMELDUNGEN = 25;

    private User $nutzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nachrichtenAbfangen();
        config()->set('userauth.two_factor_enabled', true);
        config()->set('userauth.audit_log_enabled', false);
        $this->nutzer = User::factory()->create(['email' => 'max@firma.de']);
    }

    /**
     * Meldet mehrfach (jeweils in neuer Sitzung) an und sammelt die Codes aus den Mails.
     *
     * @return list<string>
     */
    private function codesSammeln(int $anzahl): array
    {
        $codes = [];
        for ($i = 0; $i < $anzahl; $i++) {
            $this->flushSession();
            Auth::forgetGuards();
            $this->post(route('login'), ['email' => 'max@firma.de', 'password' => 'password'])
                ->assertRedirect(route('verify.index'));
            $codes[] = $this->zweiFaktorCode('max@firma.de');
            $this->assertCount($i + 1, $this->nachrichtenAn('max@firma.de'));
        }

        return $codes;
    }

    /**
     * Quelldateien des Pakets, die mit der Zwei-Faktor-Anmeldung zu tun haben.
     *
     * @return array<string, string> Pfad => Inhalt
     */
    private function zweiFaktorQuellen(): array
    {
        $quellen = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $datei) {
            if (! $datei->isFile() || $datei->getExtension() !== 'php' || str_contains($datei->getPathname(), '/Resources/views/')) {
                continue;
            }
            $inhalt = (string) file_get_contents($datei->getPathname());
            if (preg_match('/two_?factor|zweifaktor|challenge|2fa/i', $datei->getPathname().$inhalt) === 1) {
                $quellen[$datei->getPathname()] = $inhalt;
            }
        }

        $this->assertNotEmpty($quellen, 'Keine Quelldateien zur Zwei-Faktor-Anmeldung gefunden.');

        return $quellen;
    }

    // AK18
    public function test_jeder_erzeugte_code_besteht_aus_genau_6_ziffern(): void
    {
        foreach ($this->codesSammeln(self::ANMELDUNGEN) as $code) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        }
    }

    // AK18
    public function test_die_codes_wiederholen_sich_nicht(): void
    {
        $codes = $this->codesSammeln(self::ANMELDUNGEN);

        $this->assertGreaterThanOrEqual(self::ANMELDUNGEN - 2, count(array_unique($codes)), 'Die Codes sind nicht zufällig genug.');
    }

    // AK18
    public function test_die_codes_decken_den_wertebereich_nicht_nur_eine_ziffernstelle_ab(): void
    {
        $codes = $this->codesSammeln(self::ANMELDUNGEN);

        foreach ([0, 2, 5] as $stelle) {
            $ziffern = array_unique(array_map(fn (string $code): string => $code[$stelle], $codes));
            $this->assertGreaterThan(3, count($ziffern), "An Stelle {$stelle} variieren die Ziffern kaum.");
        }
    }

    // AK18 (kryptografisch sicherer Zufall)
    public function test_die_erzeugung_nutzt_random_int_und_keine_unsicheren_zufallsfunktionen(): void
    {
        $quellen = $this->zweiFaktorQuellen();

        $this->assertNotEmpty(
            array_filter($quellen, fn (string $inhalt): bool => str_contains($inhalt, 'random_int(')),
            'Der 2FA-Code muss mit random_int() erzeugt werden.',
        );
        foreach ($quellen as $pfad => $inhalt) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w>:$])(?:mt_rand|rand|mt_srand|srand|array_rand|shuffle|str_shuffle|uniqid|lcg_value)\s*\(/',
                $inhalt,
                "Unsicherer Zufall in {$pfad}.",
            );
        }
    }

    // AK18 (zeitkonstanter Vergleich)
    public function test_der_code_wird_zeitkonstant_verglichen(): void
    {
        $quellen = $this->zweiFaktorQuellen();

        $this->assertNotEmpty(
            array_filter($quellen, fn (string $inhalt): bool => preg_match('/hash_equals\s*\(|Hash::check\s*\(|password_verify\s*\(/', $inhalt) === 1),
            'Der Code muss mit hash_equals(), Hash::check() oder password_verify() verglichen werden.',
        );
    }
}
