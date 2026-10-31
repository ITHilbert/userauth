<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Head-Erweiterung des Hosts (userauth.head_view) in den Auth-Seiten mit eigenem <head>.
 */
final class AuthHeadViewTest extends TestCase
{
    private const MARKER = 'HOSTHEAD-7f3a91';

    private string $hostViewDir;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('userauth.auth_assets', ['css' => [], 'js' => []]);

        $this->hostViewDir = sys_get_temp_dir().'/userauth-head-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->hostViewDir);
        File::put(
            $this->hostViewDir.'/hostkopf.blade.php',
            '<link rel="icon" href="/favicon.svg"><meta name="host-marker" content="'.self::MARKER.'">'
        );
        View::addLocation($this->hostViewDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->hostViewDir);

        parent::tearDown();
    }

    /**
     * Jedes der vier Templates mit eigenem <head> je Theme:
     * auth (tailwind: alle Seiten), beforeLogin (bootstrap: login, forgotten),
     * tokensend/bootstrap, editwithtoken/bootstrap.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function seiten(): array
    {
        return [
            'tailwind login (layouts/auth)' => ['tailwind', '/login'],
            'tailwind passwort vergessen (layouts/auth)' => ['tailwind', '/password/forgotten'],
            'tailwind token gesendet (layouts/auth)' => ['tailwind', '/password/tokensend'],
            'tailwind passwort per token (layouts/auth)' => ['tailwind', '/password/editwithtoken/abc123/test@example.com'],
            'bootstrap login (layouts/beforeLogin)' => ['bootstrap', '/login'],
            'bootstrap passwort vergessen (layouts/beforeLogin)' => ['bootstrap', '/password/forgotten'],
            'bootstrap token gesendet (tokensend/bootstrap)' => ['bootstrap', '/password/tokensend'],
            'bootstrap passwort per token (editwithtoken/bootstrap)' => ['bootstrap', '/password/editwithtoken/abc123/test@example.com'],
        ];
    }

    private function kopf(string $html): string
    {
        $this->assertSame(1, preg_match('#<head\b[^>]*>(.*?)</head>#si', $html, $m), 'Kein <head> … </head> gefunden');

        // CSRF-Token ist je Anfrage verschieden, für den Vergleich neutralisieren.
        return (string) preg_replace('#(name="csrf-token" content=")[^"]*#', '$1X', $m[1]);
    }

    private function seiteLaden(string $theme, string $url, ?string $headView): string
    {
        config()->set('userauth.theme', $theme);
        config()->set('userauth.head_view', $headView);

        $response = $this->get($url);
        $response->assertOk();

        return (string) $response->getContent();
    }

    // AK1
    #[DataProvider('seiten')]
    public function test_inhalt_der_host_view_steht_im_head(string $theme, string $url): void
    {
        $html = $this->seiteLaden($theme, $url, 'hostkopf');

        $this->assertStringContainsString(self::MARKER, $this->kopf($html), 'Inhalt der Host-View fehlt im <head>');
        $this->assertStringContainsString('<link rel="icon" href="/favicon.svg">', $this->kopf($html));
        // Nur einmal, nicht zusätzlich im Body.
        $this->assertSame(1, substr_count($html, self::MARKER));
    }

    // AK3
    #[DataProvider('seiten')]
    public function test_ohne_einstellung_antwortet_200_ohne_zusatz_im_head(string $theme, string $url): void
    {
        // Gegenprobe: mit Einstellung greift der Aufbau.
        $mit = $this->seiteLaden($theme, $url, 'hostkopf');
        $this->assertStringContainsString(self::MARKER, $this->kopf($mit));

        $ohne = $this->seiteLaden($theme, $url, null);

        $this->assertStringNotContainsString(self::MARKER, $ohne);
        $this->assertStringNotContainsString('favicon.svg', $ohne);
        // Kopf entspricht dem Kopf mit Host-View abzüglich deren Ausgabe.
        $erwartet = str_replace('<link rel="icon" href="/favicon.svg"><meta name="host-marker" content="'.self::MARKER.'">', '', $this->kopf($mit));
        $this->assertSame(preg_replace('/\s+/', '', $erwartet), preg_replace('/\s+/', '', $this->kopf($ohne)));
    }

    // Randfall 2
    #[DataProvider('seiten')]
    public function test_leerer_wert_wirkt_wie_nicht_gesetzt(string $theme, string $url): void
    {
        $mit = $this->seiteLaden($theme, $url, 'hostkopf');
        $this->assertStringContainsString(self::MARKER, $this->kopf($mit));

        $nicht = $this->seiteLaden($theme, $url, null);
        $leer = $this->seiteLaden($theme, $url, '');

        $this->assertStringNotContainsString(self::MARKER, $leer);
        $this->assertSame($this->kopf($nicht), $this->kopf($leer));
    }

    // Randfall 1
    #[DataProvider('seiten')]
    public function test_fehlende_view_antwortet_200_ohne_ausgabe(string $theme, string $url): void
    {
        $mit = $this->seiteLaden($theme, $url, 'hostkopf');
        $this->assertStringContainsString(self::MARKER, $this->kopf($mit));

        $nicht = $this->seiteLaden($theme, $url, null);
        $fehlt = $this->seiteLaden($theme, $url, 'gibt-es-nicht.kopf');

        $this->assertStringNotContainsString('gibt-es-nicht', $fehlt);
        $this->assertSame($this->kopf($nicht), $this->kopf($fehlt));
    }

    /** @return array<string, array{0: string}> */
    public static function vierTemplates(): array
    {
        return [
            'layouts/auth' => ['layouts/auth.blade.php'],
            'layouts/beforeLogin' => ['layouts/beforeLogin.blade.php'],
            'password/tokensend/bootstrap' => ['password/tokensend/bootstrap.blade.php'],
            'password/editwithtoken/bootstrap' => ['password/editwithtoken/bootstrap.blade.php'],
        ];
    }

    private function viewPfad(string $relativ): string
    {
        return dirname(__DIR__, 2).'/src/Resources/views/'.$relativ;
    }

    /** Partial, das ein Template im <head> einbindet und das head_view ausgibt (userauth::…), sonst null. */
    private function partialImKopf(string $datei): ?string
    {
        $inhalt = (string) file_get_contents($this->viewPfad($datei));
        if (! preg_match('#<head\b.*?</head>#si', $inhalt, $kopf)) {
            return null;
        }
        preg_match_all("#@include(?:If)?\\(\\s*['\"](userauth::[^'\"]+)['\"]#", $kopf[0], $treffer);
        foreach ($treffer[1] as $name) {
            if (View::exists($name) && str_contains((string) file_get_contents(View::getFinder()->find($name)), 'head_view')) {
                return $name;
            }
        }

        return null;
    }

    // AK2
    #[DataProvider('vierTemplates')]
    public function test_template_liest_head_view_nicht_selbst(string $datei): void
    {
        $this->assertFileExists($this->viewPfad($datei));
        $this->assertStringNotContainsString('head_view', (string) file_get_contents($this->viewPfad($datei)));
    }

    // AK2
    #[DataProvider('vierTemplates')]
    public function test_template_bindet_partial_im_head_ein_das_head_view_ausgibt(string $datei): void
    {
        $partial = $this->partialImKopf($datei);
        $this->assertNotNull($partial, "{$datei} bindet im <head> kein userauth::-Partial ein");
        $this->assertTrue(View::exists($partial), "Partial {$partial} existiert nicht");

        $quelle = (string) file_get_contents(View::getFinder()->find($partial));
        $this->assertStringContainsString('userauth.head_view', $quelle);
        $this->assertStringContainsString('@includeIf', $quelle);
    }

    // AK2
    public function test_alle_vier_templates_nutzen_dasselbe_partial(): void
    {
        $partials = [];
        foreach (array_keys(self::vierTemplates()) as $name) {
            $partials[$name] = $this->partialImKopf($name.'.blade.php');
        }

        $this->assertNotContains(null, $partials, 'Ein Template bindet kein Partial ein');
        $this->assertCount(1, array_unique($partials), 'Die Templates nutzen unterschiedliche Partials');
    }

    // AK2
    public function test_head_view_wird_nur_in_einer_datei_gelesen(): void
    {
        $treffer = [];
        foreach (File::allFiles(dirname(__DIR__, 2).'/src/Resources/views') as $datei) {
            if (str_contains((string) file_get_contents($datei->getPathname()), 'head_view')) {
                $treffer[] = $datei->getRelativePathname();
            }
        }

        $this->assertCount(1, $treffer, 'head_view steht in: '.implode(', ', $treffer));
    }

    // Randfall 3
    public function test_admin_layout_des_hosts_bleibt_ohne_head_view(): void
    {
        $partial = $this->partialImKopf('layouts/auth.blade.php');
        $this->assertNotNull($partial, 'Gegenprobe: layouts/auth bindet das Partial ein');

        $afterLogin = (string) file_get_contents($this->viewPfad('layouts/afterLogin.blade.php'));
        $this->assertStringContainsString("@extends('layouts.app')", $afterLogin);
        $this->assertStringNotContainsString('head_view', $afterLogin);
        $this->assertStringNotContainsString($partial, $afterLogin);
    }

    // AK3
    public function test_standard_der_config_ist_null(): void
    {
        $config = require dirname(__DIR__, 2).'/src/Config/config.php';

        $this->assertArrayHasKey('head_view', $config);
        $this->assertNull($config['head_view']);
    }
}
