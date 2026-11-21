<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use PHPUnit\Framework\TestCase;

// testebene: reine Dateiprüfung von composer.json, README und CHANGELOG, keine App nötig

/**
 * Sprachdatei der Listen kommt aus laravel-kit >= 1.6.1 (userauth-datatable-sprachdatei, #661).
 */
final class DatatableSprachdateiTest extends TestCase
{
    private function paketdatei(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$name);
    }

    // AK1
    public function test_composer_verlangt_laravel_kit_ab_1_6_1(): void
    {
        $composer = json_decode($this->paketdatei('composer.json'), true);

        $this->assertSame('^1.6.1', $composer['require']['ithilbert/laravel-kit'] ?? null);
    }

    // AK2
    public function test_readme_nennt_publish_befehl_und_hat_keinen_kommt_aus_satz_ohne_befehl(): void
    {
        $zeilen = explode("\n", $this->paketdatei('README.md'));

        $mitDatei = array_values(array_filter($zeilen, fn (string $z): bool => str_contains($z, 'DataTable_DE.json')));
        $this->assertNotEmpty($mitDatei, 'README erwähnt DataTable_DE.json nicht');

        $this->assertStringContainsString(
            'php artisan vendor:publish --tag=laravelkit-assets',
            implode("\n", $zeilen),
            'README nennt den Publish-Befehl für DataTable_DE.json nicht'
        );

        foreach ($mitDatei as $zeile) {
            $this->assertStringNotContainsString(
                'Die Datei kommt aus `ithilbert/laravel-kit`.',
                str_replace('php artisan vendor:publish --tag=laravelkit-assets', '', $zeile),
                'Satz „kommt aus“ steht noch ohne Befehl in der README'
            );
        }
    }

    // AK3
    public function test_changelog_oberste_version_ist_3_5_3_mit_eintrag_661(): void
    {
        $changelog = $this->paketdatei('CHANGELOG.md');

        $this->assertSame(1, preg_match('/^## (\d+\.\d+\.\d+)\b.*?(?=^## |\z)/ms', $changelog, $treffer));
        $this->assertSame('3.5.3', $treffer[1], 'Oberste Version im CHANGELOG ist nicht 3.5.3');
        $this->assertStringContainsString('#661', $treffer[0]);
    }
}
