<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Architektur-Test (AK20): Passkey folgt der Schichtung von OIDC und Social Login. Die Bibliothek
 * `web-auth/webauthn-lib` (Namensraum `Webauthn\`) kennt nur Infrastructure.
 */
#[Group('arch')]
final class PasskeySchichtungTest extends TestCase
{
    private const MUSTER_WEBAUTHN = '/(?<![A-Za-z0-9_])Webauthn\\\\/';

    private const MUSTER_INFRASTRUCTURE = '/ITHilbert\\\\UserAuth\\\\Infrastructure\\\\/';

    private static function src(string $unterordner): string
    {
        return dirname(__DIR__, 3).'/src/'.$unterordner;
    }

    /** @return list<string> */
    private function phpDateien(string $ordner): array
    {
        $this->assertDirectoryExists($ordner);
        $dateien = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ordner)) as $datei) {
            if ($datei instanceof SplFileInfo && $datei->getExtension() === 'php') {
                $dateien[] = $datei->getPathname();
            }
        }

        return $dateien;
    }

    /** @return array<string, array{0: string}> */
    public static function webauthnSchreibweisen(): array
    {
        return [
            'use' => ['use Webauthn\\PublicKeyCredentialSource;'],
            'use mit Alias' => ['use Webauthn\\Foo as Bar;'],
            'voll qualifiziert' => ['$x = new \\Webauthn\\Bar();'],
            'in Typangabe' => ['public function f(\\Webauthn\\Baz $x): void {}'],
            'gruppiertes use' => ["use Webauthn\\{\n    A,\n    B,\n};"],
            'Kommentar' => ['// nutzt Webauthn\\Foo'],
        ];
    }

    // AK20 (Gegenprobe des Suchmusters: jede Schreibweise ist ein Treffer, harmlose Texte nicht)
    #[DataProvider('webauthnSchreibweisen')]
    public function test_ak20_suchmuster_erkennt_jede_schreibweise_der_bibliothek(string $quelltext): void
    {
        $this->assertSame(1, preg_match(self::MUSTER_WEBAUTHN, $quelltext), $quelltext);
        $this->assertSame(0, preg_match(self::MUSTER_WEBAUTHN, 'use ITHilbert\\UserAuth\\Application\\Passkey\\PasskeyAnmelden;'));
        $this->assertSame(0, preg_match(self::MUSTER_WEBAUTHN, 'class MyWebauthnHelper {}'));
    }

    // AK20
    public function test_ak20_domain_und_application_kennen_weder_webauthn_noch_infrastructure(): void
    {
        $dateien = [...$this->phpDateien(self::src('Domain')), ...$this->phpDateien(self::src('Application'))];
        $this->assertNotEmpty($dateien);

        foreach ($dateien as $datei) {
            $quelltext = (string) file_get_contents($datei);
            $this->assertSame(0, preg_match(self::MUSTER_WEBAUTHN, $quelltext), "{$datei} kennt Webauthn\\.");
            $this->assertSame(0, preg_match(self::MUSTER_INFRASTRUCTURE, $quelltext), "{$datei} kennt Infrastructure.");
        }
    }

    // AK20
    public function test_ak20_passkey_liegt_in_application_und_infrastructure_der_adapter_nutzt_die_bibliothek(): void
    {
        $anwendung = $this->phpDateien(self::src('Application/Passkey'));
        $adapter = $this->phpDateien(self::src('Infrastructure/Passkey'));
        $this->assertNotEmpty($anwendung, 'Application/Passkey ist leer.');
        $this->assertNotEmpty($adapter, 'Infrastructure/Passkey ist leer.');

        $namen = array_map('basename', $anwendung);
        $this->assertContains('PasskeyAnmelden.php', $namen);
        $this->assertContains('PasskeyRegistrieren.php', $namen);

        $nutztBibliothek = array_filter($adapter, fn (string $datei): bool => preg_match(self::MUSTER_WEBAUTHN, (string) file_get_contents($datei)) === 1);
        $this->assertNotEmpty($nutztBibliothek, 'Kein Adapter in Infrastructure/Passkey nutzt Webauthn\\.');
    }
}
