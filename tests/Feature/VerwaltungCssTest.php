<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use ITHilbert\UserAuth\Infrastructure\Assets\AuthAssetVersion;
use ITHilbert\UserAuth\Tests\Support\VerwaltungSeitenHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Vorkompilierte Verwaltungs-CSS: Datei, Route, Einbindung ins Host-Layout, Theme-Hinweis (kern-umstellung T1).
 */
final class VerwaltungCssTest extends TestCase
{
    use RefreshDatabase;
    use VerwaltungSeitenHilfe;

    private const LINK_PFAD = 'userauth/assets/userauth-verwaltung.css';

    private function paket(): string
    {
        return dirname(__DIR__, 2);
    }

    private function cssPfad(): string
    {
        return $this->paket().'/src/Resources/assets/css/userauth-verwaltung.css';
    }

    private function css(): string
    {
        $this->assertFileExists($this->cssPfad(), 'userauth-verwaltung.css ist vorkompiliert vorhanden');

        return (string) file_get_contents($this->cssPfad());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function seitenMitLayouts(): array
    {
        $faelle = [];
        foreach (['stack', 'section', 'beides'] as $layout) {
            foreach (self::verwaltungSeiten() as $seite => [$name]) {
                $faelle[$layout.' '.$seite] = [$layout, $name];
            }
        }

        return $faelle;
    }

    // ------------------------------------------------------------------ AK1

    // AK1
    public function test_ak1_css_enthaelt_die_regeln_und_kein_preflight(): void
    {
        $css = $this->css();

        foreach (['kit-card', 'kit-button', 'kit-input-text__input', 'kit-tabs__tab', 'kit-dialog__content', 'kit-combobox__list', 'userauth-zeile', 'userauth-zeile__label', 'userauth-zeile__feld'] as $klasse) {
            $this->assertMatchesRegularExpression('/\.'.preg_quote($klasse, '/').'(?![\w-])/', $css, 'Regel für .'.$klasse);
        }
        $this->assertMatchesRegularExpression('/\.d-none\s*\{[^}]*display:\s*none/', $css, '.d-none blendet aus');

        // Gegenprobe: die Suche erkennt Preflight, userauth.css (mit Preflight) enthält ihn
        $mitPreflight = (string) file_get_contents($this->paket().'/src/Resources/assets/css/userauth.css');
        $this->assertStringContainsString('::file-selector-button', $mitPreflight);
        $this->assertStringNotContainsString('::file-selector-button', $css, 'kein Preflight');
    }

    // AK1
    public function test_ak1_quelle_bindet_das_kit_ein_und_package_json_baut_beide_dateien(): void
    {
        $this->assertFileExists($this->paket().'/resources-src/verwaltung.css');
        $quelle = (string) file_get_contents($this->paket().'/resources-src/verwaltung.css');
        $this->assertStringContainsString('vue3/kit.css', $quelle);
        $this->assertStringContainsString('vue3/themes/tailwind.css', $quelle);
        // kein Preflight: weder `tailwindcss/preflight.css` noch der Sammelimport `tailwindcss` (der Preflight einschließt)
        $einbindung = '/@import\s+["\'](tailwindcss(\/preflight[^"\']*)?)["\']/';
        $this->assertSame(1, preg_match($einbindung, (string) file_get_contents($this->paket().'/resources-src/userauth.css')), 'Gegenprobe: userauth.css bindet Preflight ein und wird erkannt');
        $this->assertSame(0, preg_match($einbindung, $quelle), 'verwaltung.css bindet keinen Preflight ein');

        $skript = json_decode((string) file_get_contents($this->paket().'/package.json'), true)['scripts']['build:css'] ?? '';
        $this->assertStringContainsString('userauth.css', $skript, 'baut userauth.css weiter');
        $this->assertStringContainsString('resources-src/verwaltung.css', $skript);
        $this->assertStringContainsString('userauth-verwaltung.css', $skript);
    }

    // ------------------------------------------------------------------ AK2

    // AK2
    public function test_ak2_route_liefert_die_datei_und_die_alte_css_route_bleibt(): void
    {
        $antwort = $this->get(route('userauth.assets.verwaltung'))->assertOk();

        $this->assertStringStartsWith('text/css', (string) $antwort->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $antwort->headers->get('Cache-Control'));
        $this->assertSame($this->css(), $antwort->streamedContent() ?: $antwort->getContent());

        $alt = $this->get(route('userauth.assets.css'))->assertOk();
        $this->assertSame((string) file_get_contents($this->paket().'/src/Resources/assets/css/userauth.css'), $alt->streamedContent() ?: $alt->getContent());
    }

    // AK2
    public function test_ak2_version_ist_der_zwoelfstellige_hash_der_datei(): void
    {
        $assets = new AuthAssetVersion;

        $this->assertSame(realpath($this->cssPfad()), realpath($assets->verwaltungCssPath()));
        $this->assertSame(substr(hash_file('sha256', $this->cssPfad()), 0, 12), $assets->verwaltungCssVersion());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $assets->verwaltungCssVersion());
    }

    // Randfall 2
    public function test_randfall2_fehlende_datei_gibt_404_version_null_und_seiten_rendern_weiter(): void
    {
        $this->verwaltungAufbauen('cssviews/stack');
        $this->assertFileExists($this->cssPfad(), 'Aufbau: Datei da, bevor sie entfernt wird');
        $this->get(route('userauth.assets.verwaltung'))->assertOk();

        $versteckt = $this->cssPfad().'.weg';
        rename($this->cssPfad(), $versteckt);
        try {
            $this->get(route('userauth.assets.verwaltung'))->assertNotFound();
            $this->assertSame('0', (new AuthAssetVersion)->verwaltungCssVersion());
            $this->actingAs($this->admin)->get(route('role.create'))->assertOk();
        } finally {
            rename($versteckt, $this->cssPfad());
        }
    }

    // ------------------------------------------------------------------ AK3

    // AK3
    #[DataProvider('seitenMitLayouts')]
    public function test_ak3_seite_bindet_die_verwaltungs_css_genau_einmal_ein(string $layout, string $seite): void
    {
        $this->verwaltungAufbauen('cssviews/'.$layout);
        $hash = substr(hash('sha256', $this->css()), 0, 12);

        $html = $this->verwaltungSeite($seite);

        $this->assertStringContainsString('<h1', $html, 'Aufbau: Verwaltungsseite im Host-Layout');
        $links = $this->verwaltungDom($html)->query('//link[@rel="stylesheet"][contains(@href, "'.self::LINK_PFAD.'")]');
        $this->assertSame(1, $links->length, 'genau ein <link> auf die Verwaltungs-CSS');
        $this->assertStringEndsWith(self::LINK_PFAD.'?v='.$hash, $links->item(0)->getAttribute('href'));
    }

    // AK3
    public function test_ak3_login_seite_enthaelt_die_verwaltungs_css_nicht(): void
    {
        config(['userauth.theme' => 'tailwind']);

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('userauth/assets/userauth.css', $html, 'Gegenprobe: Login-Seite bindet ihre eigene CSS');
        $this->assertStringNotContainsString(self::LINK_PFAD, $html);
    }

    // ------------------------------------------------------------------ AK9

    /** @return array<string, array{0: string, 1: string}> */
    public static function fremdeThemes(): array
    {
        return ['tailwind user.create' => ['tailwind', 'user.create'], 'bootstrap5 role.edit' => ['bootstrap5', 'role.edit']];
    }

    // AK9
    #[DataProvider('fremdeThemes')]
    public function test_ak9_anderes_theme_loggt_genau_eine_warnung_je_request_und_antwortet_200(string $theme, string $seite): void
    {
        $this->verwaltungAufbauen('cssviews/stack', $theme);
        $warnungen = [];
        config(['logging.default' => 'null']);
        Log::listen(function (MessageLogged $e) use (&$warnungen): void {
            if ($e->level === 'warning' && str_contains($e->message, 'userauth') && str_contains($e->message, 'laravelkit.theme=kern')) {
                $warnungen[] = $e->message;
            }
        });

        $this->verwaltungSeite($seite);
        $this->assertCount(1, $warnungen, 'eine Warnung im ersten Request');
        $this->verwaltungSeite($seite);
        $this->assertCount(2, $warnungen, 'je Request eine, nicht einmal je Prozess');
    }

    // AK9
    public function test_ak9_mit_theme_kern_gibt_es_keine_warnung(): void
    {
        $this->verwaltungAufbauen('cssviews/stack', 'kern');
        $warnungen = [];
        config(['logging.default' => 'null']);
        Log::listen(function (MessageLogged $e) use (&$warnungen): void {
            if (str_contains($e->message, 'laravelkit.theme=kern')) {
                $warnungen[] = $e->message;
            }
        });

        $html = $this->verwaltungSeite('user.create');

        $this->assertStringContainsString('<h1', $html, 'Aufbau: Seite gerendert, Log lauscht');
        $this->assertSame([], $warnungen);
    }
}
