<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Tests\TestCase;

/**
 * Die Auth-Views dürfen nicht von der CSS des Host-Projekts abhängen:
 * - Tailwind-Theme: Package liefert eigene, vorkompilierte CSS über eine versionierte Route.
 * - Beide Themes: zusätzliche Host-Assets über config('userauth.auth_assets').
 */
final class AuthViewAssetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('userauth.auth_assets', ['css' => [], 'js' => []]);
    }

    private function packageCssPath(): string
    {
        return dirname(__DIR__, 2).'/src/Resources/assets/css/userauth.css';
    }

    public function test_compiled_package_css_exists_and_contains_classes_of_tailwind_views(): void
    {
        $this->assertFileExists($this->packageCssPath());
        $css = (string) file_get_contents($this->packageCssPath());

        foreach (['.max-w-md', '.bg-indigo-600', '.rounded-xl', '.min-h-screen', '.bg-gray-100'] as $class) {
            $this->assertStringContainsString($class, $css, "Klasse {$class} fehlt in userauth.css");
        }
    }

    public function test_css_route_serves_package_css_with_long_term_caching(): void
    {
        $response = $this->get(route('userauth.assets.css'));

        $response->assertOk();
        $this->assertStringStartsWith('text/css', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertSame((string) file_get_contents($this->packageCssPath()), $response->streamedContent() ?: $response->getContent());
    }

    public function test_css_route_does_not_require_authentication(): void
    {
        $this->get(route('userauth.assets.css'))->assertOk();
    }

    public function test_tailwind_login_loads_versioned_package_css(): void
    {
        config()->set('userauth.theme', 'tailwind');
        $version = substr(hash_file('sha256', $this->packageCssPath()), 0, 12);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('userauth.assets.css', ['v' => $version]), false);
    }

    public function test_bootstrap_login_does_not_load_package_tailwind_css(): void
    {
        config()->set('userauth.theme', 'bootstrap');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee(route('userauth.assets.css'), false);
    }

    public function test_configured_auth_assets_are_loaded_in_bootstrap_theme(): void
    {
        config()->set('userauth.theme', 'bootstrap');
        config()->set('userauth.auth_assets', [
            'css' => ['assets/css/theme.min.css'],
            'js' => ['assets/js/config.js'],
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<link rel="stylesheet" href="'.asset('assets/css/theme.min.css').'">', false)
            ->assertSee('<script src="'.asset('assets/js/config.js').'" defer></script>', false);
    }

    public function test_configured_auth_assets_are_loaded_in_tailwind_theme(): void
    {
        config()->set('userauth.theme', 'tailwind');
        config()->set('userauth.auth_assets', ['css' => ['css/brand.css'], 'js' => []]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<link rel="stylesheet" href="'.asset('css/brand.css').'">', false);
    }

    public function test_auth_assets_are_escaped(): void
    {
        config()->set('userauth.theme', 'tailwind');
        config()->set('userauth.auth_assets', ['css' => ['x.css"><script>alert(1)</script>'], 'js' => []]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_password_forgotten_page_renders_in_both_themes(): void
    {
        foreach (['tailwind', 'bootstrap'] as $theme) {
            config()->set('userauth.theme', $theme);
            $this->get(route('password.forgotten'))->assertOk();
        }
    }
}
