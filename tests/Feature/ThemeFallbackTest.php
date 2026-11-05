<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Support\ViewErrorBag;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Theme-Wahl der acht Einstiegs-Views (#606): Fallback = Paket-Standard tailwind,
 * gesetzte Themes unverändert, Wahl nur an einer Stelle.
 */
final class ThemeFallbackTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function einstiegsViews(): array
    {
        $namen = [
            'login',
            'password.editwithtoken',
            'password.forgotten',
            'password.tokensend',
            'registrierung.formular',
            'registrierung.bestaetigung',
            'social.confirm',
            'social.accounts',
        ];

        return array_combine($namen, array_map(static fn (string $n): array => [$n], $namen));
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function leereThemes(): array
    {
        return ['nicht gesetzt (null)' => [null], 'leer gesetzt' => ['']];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('userauth.auth_assets', ['css' => [], 'js' => []]);
    }

    private function rendern(string $name): string
    {
        $provider = new class
        {
            public string $value = 'github';

            public function label(): string
            {
                return 'GitHub';
            }
        };

        return view('userauth::'.$name, [
            'errors' => new ViewErrorBag,
            'error' => null,
            'message' => null,
            'email' => 'test@example.com',
            'token' => 'abc123',
            'provider' => $provider,
            'providers' => [$provider],
            'linked' => [],
            'availableSocialProviders' => [],
            'registrierungAktiv' => true,
        ])->render();
    }

    private function istTailwind(string $html): bool
    {
        return str_contains($html, route('userauth.assets.css'));
    }

    // AK5 (#606)
    #[DataProvider('einstiegsViews')]
    public function test_ohne_theme_wird_tailwind_template_gerendert(string $name): void
    {
        // Gegenprobe: das Merkmal unterscheidet die Themes
        config()->set('userauth.theme', 'bootstrap');
        $this->assertFalse($this->istTailwind($this->rendern($name)), "{$name} bootstrap");
        config()->set('userauth.theme', 'tailwind');
        $this->assertTrue($this->istTailwind($this->rendern($name)), "{$name} tailwind");

        foreach (self::leereThemes() as $label => [$wert]) {
            config()->set('userauth.theme', $wert);
            $this->assertTrue($this->istTailwind($this->rendern($name)), "{$name}: theme {$label} muss tailwind sein");
        }
    }

    // AK5 (#606)
    public function test_login_route_mit_leerem_theme_rendert_tailwind(): void
    {
        config()->set('userauth.theme', 'bootstrap');
        $this->assertStringNotContainsString(route('userauth.assets.css'), $this->get(route('login'))->assertOk()->getContent());

        foreach (self::leereThemes() as [$wert]) {
            config()->set('userauth.theme', $wert);
            $this->get(route('login'))->assertOk()->assertSee(route('userauth.assets.css'), false);
        }
    }

    // AK6 (#606)
    #[DataProvider('einstiegsViews')]
    public function test_gesetztes_theme_bleibt_unveraendert(string $name): void
    {
        config()->set('userauth.theme', 'tailwind');
        $this->assertTrue($this->istTailwind($this->rendern($name)), "{$name} tailwind");

        config()->set('userauth.theme', 'bootstrap');
        $this->assertFalse($this->istTailwind($this->rendern($name)), "{$name} bootstrap");
    }

    // AK7 (#606)
    public function test_einstiegs_views_lesen_theme_nicht_selbst(): void
    {
        $gelesen = 0;
        foreach (array_keys(self::einstiegsViews()) as $name) {
            $datei = dirname(__DIR__, 2).'/src/Resources/views/'.str_replace('.', '/', $name).'.blade.php';
            $this->assertFileExists($datei);
            $inhalt = (string) file_get_contents($datei);
            $this->assertNotSame('', $inhalt, "{$name} leer gelesen");
            $gelesen++;
            $this->assertStringNotContainsString('userauth.theme', $inhalt, "{$name} liest userauth.theme selbst");
        }
        $this->assertSame(8, $gelesen);
    }
}
