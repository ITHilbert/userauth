<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use PHPUnit\Framework\TestCase;

// testebene: reine Dateiprüfung von composer.json, README und CHANGELOG, keine App nötig

/**
 * Version, README und CHANGELOG zu userauth 4.0.0 (kern-umstellung T4).
 */
final class KernDokuTest extends TestCase
{
    private function paketdatei(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$name);
    }

    // AK11
    public function test_ak11_composer_verlangt_laravel_kit_1_14(): void
    {
        $composer = json_decode($this->paketdatei('composer.json'), true);

        $this->assertSame('^1.14', $composer['require']['ithilbert/laravel-kit'] ?? null);
    }

    // AK11
    public function test_ak11_readme_hat_den_abschnitt_upgrade_auf_4_0_mit_allen_punkten(): void
    {
        $readme = $this->paketdatei('README.md');

        $this->assertSame(1, preg_match('/^##+ Upgrade auf 4\.0\b.*?(?=^## |\z)/ms', $readme, $abschnitt), 'Abschnitt „Upgrade auf 4.0“ fehlt');
        foreach (['kern', 'initLoeschen()', 'mountInseln()', 'userauth.assets.verwaltung', 'build:css'] as $muss) {
            $this->assertStringContainsString($muss, $abschnitt[0], 'Upgrade auf 4.0 nennt '.$muss);
        }
        $this->assertMatchesRegularExpression('/laravelkit\.theme/', $abschnitt[0], 'Einstellung laravelkit.theme');
        $this->assertMatchesRegularExpression('/nicht mehr (nötig|erforderlich|notwendig)/u', $abschnitt[0]);
    }

    // AK11
    public function test_ak11_readme_nennt_dialog_delete_nirgends_als_voraussetzung(): void
    {
        $readme = $this->paketdatei('README.md');
        $this->assertStringContainsString('Upgrade auf 4.0', $readme, 'Aufbau: Abschnitt da');

        foreach (preg_split('/\n\s*\n/', $readme) ?: [] as $absatz) {
            if (! str_contains($absatz, 'dialog-delete')) {
                continue;
            }
            // Die Vue-Insel `dialog-delete` ist nicht mehr nötig; die Blade-Component `<x-kit::dialog-delete>` ist der Lösch-Dialog (#2449)
            $this->assertMatchesRegularExpression('/nicht mehr (nötig|erforderlich|notwendig)|entfällt|entfallen|<x-kit::dialog-delete>/u', $absatz, 'dialog-delete steht nur als „nicht mehr nötig“ oder als Blade-Component in der README: '.mb_substr($absatz, 0, 80));
        }
    }

    // AK11
    public function test_ak11_changelog_hat_4_0_0_mit_geaendert_inkompatibel(): void
    {
        $changelog = $this->paketdatei('CHANGELOG.md');

        $this->assertSame(1, preg_match('/^## 4\.0\.0\b.*?(?=^## |\z)/ms', $changelog, $eintrag), 'Eintrag 4.0.0 fehlt');
        $this->assertStringContainsString('### Geändert (inkompatibel)', $eintrag[0]);
    }
}
