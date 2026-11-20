<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Vite;
use ITHilbert\UserAuth\Tests\TestCase;
use ReflectionProperty;

/**
 * Bugfix #1938: Die Vite-Einträge der Auth-Layouts dürfen nicht fest verdrahtet sein.
 * Hosts, deren Manifest andere Einträge hat, bekamen 500 "Unable to locate file in Vite manifest".
 */
final class ViteEintraegeTest extends TestCase
{
    private string $manifestPfad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPfad = public_path('build/manifest.json');
        $this->manifestEntfernen();
        config()->set('userauth.auth_assets', ['css' => [], 'js' => []]);
    }

    protected function tearDown(): void
    {
        $this->manifestEntfernen();
        parent::tearDown();
    }

    private function manifestEntfernen(): void
    {
        if (file_exists($this->manifestPfad)) {
            unlink($this->manifestPfad);
        }
        // Vite hält gelesene Manifeste statisch im Speicher.
        $cache = new ReflectionProperty(Vite::class, 'manifests');
        $cache->setValue(null, []);
    }

    /** @param  list<string>  $eintraege */
    private function manifestSchreiben(array $eintraege): void
    {
        $manifest = [];
        foreach ($eintraege as $eintrag) {
            $name = pathinfo($eintrag, PATHINFO_FILENAME);
            $endung = pathinfo($eintrag, PATHINFO_EXTENSION);
            $manifest[$eintrag] = [
                'file' => "assets/{$name}-abc123.{$endung}",
                'src' => $eintrag,
                'isEntry' => true,
            ];
        }
        if (! is_dir(dirname($this->manifestPfad))) {
            mkdir(dirname($this->manifestPfad), 0777, true);
        }
        file_put_contents($this->manifestPfad, json_encode($manifest));
    }

    // AK1
    public function test_fehlender_standard_eintrag_im_manifest_fuehrt_nicht_zu_500_und_vorhandene_werden_geladen(): void
    {
        $this->manifestSchreiben(['resources/js/app.js']);

        foreach (['tailwind', 'bootstrap'] as $theme) {
            config()->set('userauth.theme', $theme);

            foreach (['password.forgotten', 'login'] as $route) {
                $this->get(route($route))
                    ->assertOk()
                    ->assertSee('assets/app-abc123.js', false)
                    ->assertDontSee('assets/app-abc123.css', false);
            }
        }
    }

    // AK2
    public function test_vite_entries_config_bestimmt_die_geladenen_eintraege(): void
    {
        $this->assertSame(
            ['resources/css/app.css', 'resources/js/app.js'],
            config('userauth.vite_entries'),
        );

        $this->manifestSchreiben(['resources/css/tailwind.css', 'resources/js/app.js', 'resources/css/app.css']);
        config()->set('userauth.vite_entries', ['resources/css/tailwind.css', 'resources/js/app.js', 'resources/scss/app.scss']);

        $this->get(route('password.forgotten'))
            ->assertOk()
            ->assertSee('assets/tailwind-abc123.css', false)
            ->assertSee('assets/app-abc123.js', false)
            // vorhanden im Manifest, aber nicht in der Liste: wird nicht geladen
            ->assertDontSee('assets/app-abc123.css', false);
    }

    // AK3
    public function test_manifest_ohne_passenden_eintrag_rendert_200_ohne_vite_ausgabe(): void
    {
        $this->manifestSchreiben(['resources/css/fremd.css']);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('build/assets/', false);
    }

    // AK4
    public function test_alle_standard_eintraege_im_manifest_werden_wie_bisher_geladen(): void
    {
        $this->manifestSchreiben(['resources/css/app.css', 'resources/js/app.js']);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('build/assets/app-abc123.css', false)
            ->assertSee('build/assets/app-abc123.js', false);
    }
}
