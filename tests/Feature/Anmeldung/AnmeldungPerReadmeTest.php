<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Anmeldung;

use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AK13 (Dokumentation): README und CHANGELOG zu `anmeldung_per` (userauth-anmeldung-per, #1311).
 * Geprüft werden nur Begriffe, kein Wortlaut.
 */
final class AnmeldungPerReadmeTest extends TestCase
{
    private function datei(string $name): string
    {
        $inhalt = file_get_contents(dirname(__DIR__, 3).'/'.$name);
        $this->assertIsString($inhalt);

        return $inhalt;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function begriffe(): array
    {
        return [
            'Config-Schlüssel' => ['anmeldung_per'],
            'Umgebungsvariable' => ['USERAUTH_ANMELDUNG_PER'],
            'Spalte' => ['benutzername'],
            'Index auf users.email' => ['users_email_unique'],
        ];
    }

    // AK13
    #[DataProvider('begriffe')]
    public function test_ak13_readme_nennt_den_begriff(string $begriff): void
    {
        $this->assertStringContainsString($begriff, $this->datei('README.md'));
    }

    // AK13
    public function test_ak13_readme_beschreibt_den_modus_je_einstellung_beim_config_schluessel(): void
    {
        $readme = $this->datei('README.md');
        $abschnitte = [];
        $position = 0;
        while (($treffer = stripos($readme, 'anmeldung_per', $position)) !== false) {
            $abschnitte[] = substr($readme, max(0, $treffer - 300), 2000);
            $position = $treffer + 1;
        }
        $this->assertNotEmpty($abschnitte, 'Die README nennt anmeldung_per nicht.');

        $vollstaendig = array_filter($abschnitte, static fn (string $a): bool => stripos($a, 'email') !== false && stripos($a, 'benutzername') !== false && stripos($a, 'beides') !== false);
        $this->assertNotEmpty($vollstaendig, 'Kein Abschnitt nennt email, benutzername und beides zusammen mit anmeldung_per.');
    }

    // AK13
    public function test_ak13_readme_vermerkt_das_audit_log_verhalten_bei_der_kennung(): void
    {
        $readme = $this->datei('README.md');

        $this->assertStringContainsString('Audit', $readme);
        $this->assertMatchesRegularExpression('/Audit[\s\S]{0,400}(benutzername|kennung)|(benutzername|kennung)[\s\S]{0,400}Audit/i', $readme, 'Audit-Log und Kennung stehen nicht zusammen in der README.');
    }

    // AK13
    public function test_ak13_changelog_nennt_die_version(): void
    {
        $this->assertStringContainsString('3.3.0', $this->datei('CHANGELOG.md'));
    }
}
