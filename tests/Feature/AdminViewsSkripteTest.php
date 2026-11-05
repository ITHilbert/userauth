<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Skripte der Admin-Views laufen in jedem Host-Layout (userauth-admin-views-skripte, Dashboard #457).
 * Das Host-Layout `layouts.app` kommt je Variante aus `tests/Fixtures/skripteviews/<variante>/`:
 * `stack` gibt nur `@stack('scripts')` aus, `yield` nur `@yield('js')`, `beides` beide.
 * Randfall 1 (weder Stack noch Section) ist nicht Teil der Spec und bleibt ungetestet.
 */
final class AdminViewsSkripteTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_EDITOR = 4;

    /** Seite → Marker, der das Skript der Seite eindeutig erkennt. */
    private const MARKER = [
        'user.index' => '.DataTable(',
        'role.index' => '.DataTable(',
        'permission.index' => '.DataTable(',
        'role.create' => 'vendor/userauth/js/role.js',
        'role.edit' => 'vendor/userauth/js/role.js',
        'permission.create' => 'vendor/userauth/js/permission.js',
        'permission.edit' => 'vendor/userauth/js/permission.js',
    ];

    private const LISTEN = ['user.index', 'role.index', 'permission.index'];

    private User $admin;

    private function layout(string $variante): void
    {
        View::addLocation(__DIR__.'/../Fixtures/views');
        View::getFinder()->prependLocation(__DIR__.'/../Fixtures/skripteviews/'.$variante);
        config(['laravelkit.theme' => 'tailwind']);

        $jetzt = now();
        foreach ([[1, 'admin', 'Admin'], [self::ROLLE_EDITOR, 'editor', 'Editor']] as [$id, $name, $anzeige]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => $anzeige, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }
        DB::table('permissions_groups')->insert([
            'id' => 1, 'group_name' => 'user', 'group_display' => 'Gruppe Nutzer', 'is_group' => 0,
            'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);
        foreach (['create', 'read', 'edit', 'delete'] as $art) {
            DB::table('permissions')->insert([
                'permission' => 'user_'.$art, 'permission_display' => 'Recht '.$art,
                'group_id' => 1, 'crud' => $art, 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }

        $this->admin = User::factory()->create(['role_id' => 1, 'name' => 'Admin Nutzer', 'email' => 'admin@example.test']);
    }

    // ------------------------------------------------------------------ Datenanbieter

    /** @return array<string, array{0: string}> */
    public static function seiten(): array
    {
        $faelle = [];
        foreach (array_keys(self::MARKER) as $seite) {
            $faelle[$seite] = [$seite];
        }

        return $faelle;
    }

    /** @return array<string, array{0: string}> */
    public static function listen(): array
    {
        $faelle = [];
        foreach (self::LISTEN as $seite) {
            $faelle[$seite] = [$seite];
        }

        return $faelle;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function listenMitLayouts(): array
    {
        $faelle = [];
        foreach (['stack', 'yield', 'beides'] as $variante) {
            foreach (self::LISTEN as $seite) {
                $faelle[$variante.' '.$seite] = [$variante, $seite];
            }
        }

        return $faelle;
    }

    // ------------------------------------------------------------------ Hilfen

    private function url(string $seite): string
    {
        return match ($seite) {
            'user.index' => route('user'),
            'role.index' => route('role'),
            'role.create' => route('role.create'),
            'role.edit' => route('role.edit', self::ROLLE_EDITOR),
            'permission.index' => route('permission'),
            'permission.create' => route('permission.create'),
            'permission.edit' => route('permission.edit', 1),
        };
    }

    private function seite(string $seite): string
    {
        return $this->actingAs($this->admin)->get($this->url($seite))->assertOk()->getContent();
    }

    /** Die Seite ist die Verwaltungsseite im Host-Layout (Aufbau greift), das Skript der Seite steht genau einmal da. */
    private function assertSkriptGenauEinmal(string $seite): void
    {
        $html = $this->seite($seite);

        $this->assertStringContainsString('<h1', $html, 'Aufbau: Verwaltungsseite ist im Host-Layout gerendert');
        $this->assertSame(1, substr_count($html, self::MARKER[$seite]), 'Skript der Seite '.$seite.' steht genau einmal in der Seite');
    }

    /**
     * Skripte der Seite in Dokument-Reihenfolge als Manifest für den Lauf; `permission.js` kommt aus dem Paket statt per URL.
     * Skripte mit defer/async/type=module laufen erst mit dem Bundle.
     *
     * @return list<array{typ: string, code: string, src: string, spaeter: bool}>
     */
    private function manifest(string $html): array
    {
        preg_match_all('#<script([^>]*)>(.*?)</script>#is', $html, $treffer, PREG_SET_ORDER);
        $liste = [];
        foreach ($treffer as [, $attribute, $inhalt]) {
            $spaeter = preg_match('/\b(defer|async)\b|type\s*=\s*["\']?module/i', $attribute) === 1;
            if (preg_match('/\ssrc\s*=\s*["\']([^"\']*)["\']/i', $attribute, $quelle) === 1) {
                if (str_ends_with($quelle[1], 'permission.js')) {
                    $liste[] = ['typ' => 'src', 'code' => (string) file_get_contents(__DIR__.'/../../src/Resources/assets/js/permission.js'), 'src' => $quelle[1], 'spaeter' => $spaeter];
                }

                continue;
            }
            $liste[] = ['typ' => 'inline', 'code' => $inhalt, 'src' => '', 'spaeter' => $spaeter];
        }

        return $liste;
    }

    /**
     * Führt die Skripte der Seite aus wie bei einem Host mit Modul-Bundle (`$` erst nach dem Laden).
     *
     * @return array{fehler: ?string, fehlerVorBundle: ?string, aufrufe: list<mixed>, optionen: list<array<string, mixed>>, funktionen: list<string>}
     */
    private function skriptLaufen(string $seite): array
    {
        $html = $this->seite($seite);
        $manifest = $this->manifest($html);
        if (in_array($seite, self::LISTEN, true)) {
            $this->assertCount(1, array_filter($manifest, fn (array $s) => $s['typ'] === 'inline'), 'genau ein Inline-Skript auf der Seite');
        }

        return $this->manifestLaufen($manifest);
    }

    /**
     * @param  list<array{typ: string, code: string, src: string, spaeter: bool}>  $manifest
     * @return array{fehler: ?string, fehlerVorBundle: ?string, aufrufe: list<mixed>, optionen: list<array<string, mixed>>, funktionen: list<string>}
     */
    private function manifestLaufen(array $manifest): array
    {
        $datei = tempnam(sys_get_temp_dir(), 'userauth-skript-');
        $this->assertNotFalse($datei);

        try {
            file_put_contents($datei, json_encode($manifest, JSON_THROW_ON_ERROR));
            $ausgabe = shell_exec('node '.escapeshellarg(__DIR__.'/../Fixtures/inline-skript-lauf.mjs').' '.escapeshellarg($datei).' 2>&1');
        } finally {
            @unlink($datei);
        }

        $ergebnis = json_decode((string) $ausgabe, true);
        $this->assertIsArray($ergebnis, 'Lauf des Skripts lieferte JSON, war: '.$ausgabe);

        return $ergebnis;
    }

    // ================================================================== AK1 — nur @stack('scripts')

    // AK1
    #[DataProvider('seiten')]
    public function test_ak1_layout_mit_stack_scripts_enthaelt_das_skript_der_seite_genau_einmal(string $seite): void
    {
        $this->layout('stack');

        $this->assertSkriptGenauEinmal($seite);
    }

    // ================================================================== AK2 — nur @yield('js')

    // AK2
    #[DataProvider('seiten')]
    public function test_ak2_layout_mit_yield_js_enthaelt_das_skript_der_seite_genau_einmal(string $seite): void
    {
        $this->layout('yield');

        $this->assertSkriptGenauEinmal($seite);
    }

    // ================================================================== Randfall 2 — beides

    // Randfall 2
    #[DataProvider('seiten')]
    public function test_randfall2_layout_mit_stack_und_yield_enthaelt_das_skript_der_seite_genau_einmal(string $seite): void
    {
        $this->layout('beides');

        $this->assertSkriptGenauEinmal($seite);
    }

    // ================================================================== AK3 — nach dem Bundle

    // AK3
    #[DataProvider('listenMitLayouts')]
    public function test_ak3_inline_initialisierung_wirft_vor_dem_bundle_keinen_reference_error(string $variante, string $seite): void
    {
        $this->layout($variante);

        $lauf = $this->skriptLaufen($seite);

        $this->assertNull($lauf['fehlerVorBundle'], 'Das Skript darf `$` nicht beim Einlesen aufrufen');
    }

    // AK3
    #[DataProvider('listenMitLayouts')]
    public function test_ak3_inline_initialisierung_laeuft_nach_dem_bundle(string $variante, string $seite): void
    {
        $this->layout($variante);

        $lauf = $this->skriptLaufen($seite);

        $this->assertNull($lauf['fehler'], 'Nach dem Bundle läuft die Initialisierung ohne Fehler');
        $this->assertContains('.data-table', $lauf['aufrufe'], 'Tabelle `.data-table` wird initialisiert');
        $this->assertCount(1, $lauf['optionen'], 'DataTable genau einmal initialisiert');
    }

    // AK3
    public function test_ak3_gegenprobe_permission_js_ohne_bundle_wirft_reference_error(): void
    {
        $code = (string) file_get_contents(__DIR__.'/../../src/Resources/assets/js/permission.js');

        $lauf = $this->manifestLaufen([['typ' => 'src', 'code' => $code, 'src' => 'permission.js', 'spaeter' => false]]);

        $this->assertStringContainsString('ReferenceError', (string) $lauf['fehlerVorBundle'], 'Aufbau: ohne Verzögerung scheitert permission.js ohne `$`');
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function permissionSeitenMitLayouts(): array
    {
        $faelle = [];
        foreach (['stack', 'yield', 'beides'] as $variante) {
            foreach (['permission.create', 'permission.edit'] as $seite) {
                $faelle[$variante.' '.$seite] = [$variante, $seite];
            }
        }

        return $faelle;
    }

    // AK3
    // Randfall 3
    #[DataProvider('permissionSeitenMitLayouts')]
    public function test_randfall3_permission_js_wirft_vor_dem_bundle_keinen_reference_error(string $variante, string $seite): void
    {
        $this->layout($variante);

        $lauf = $this->skriptLaufen($seite);

        $this->assertNull($lauf['fehlerVorBundle'], 'permission.js darf `$` nicht vor dem Bundle aufrufen');
    }

    // AK3
    // Randfall 3
    #[DataProvider('permissionSeitenMitLayouts')]
    public function test_randfall3_permission_js_laeuft_nach_dem_bundle(string $variante, string $seite): void
    {
        $this->layout($variante);

        $lauf = $this->skriptLaufen($seite);

        $this->assertNull($lauf['fehler'], 'Nach dem Bundle läuft permission.js ohne Fehler');
        $this->assertContains('input[name="group_display"]', $lauf['aufrufe'], 'permission.js hat mit `$` gearbeitet');
        $this->assertSame(['showSinglePermissions', 'newPermission'], $lauf['funktionen'], 'globale Funktionen von permission.js stehen bereit');
    }

    // ================================================================== Randfälle 4 bis 6 — Host-Section js und Randfall 7 (nie leere Seite)

    private const OHNE_SKRIPTE = ['user.create', 'user.edit', 'password.edit'];

    /** @return array<string, array{0: string, 1: string}> */
    public static function hostSectionsOhneSkripte(): array
    {
        $faelle = [];
        foreach (['hostsection' => 'HOSTJS', 'hostdefault' => 'DEFAULTJS'] as $variante => $marker) {
            foreach (self::OHNE_SKRIPTE as $seite) {
                $faelle[$variante.' '.$seite] = [$variante, $seite, $marker];
            }
        }

        return $faelle;
    }

    private function urlOhneSkripte(string $seite): string
    {
        return match ($seite) {
            'user.create' => route('user.create'),
            'user.edit' => route('user.edit', $this->admin->id),
            'password.edit' => route('password.edit'),
        };
    }

    // Randfall 5
    #[DataProvider('hostSectionsOhneSkripte')]
    public function test_randfall5_seite_ohne_skripte_laesst_host_section_js_und_default_in_der_seite(string $variante, string $seite, string $marker): void
    {
        $this->layout($variante);

        $html = $this->actingAs($this->admin)->get($this->urlOhneSkripte($seite))->assertOk()->getContent();

        $this->assertStringContainsString('<h1', $html, 'Aufbau: Verwaltungsseite ist im Host-Layout gerendert');
        $this->assertSame(1, substr_count($html, $marker), $marker.' des Host-Layouts steht genau einmal in der Seite');
    }

    // Randfall 6
    #[DataProvider('seiten')]
    public function test_randfall6_seite_mit_skripten_behaelt_den_inhalt_der_host_section_js(string $seite): void
    {
        $this->layout('hostsection');

        $html = $this->seite($seite);

        $this->assertStringContainsString('<h1', $html, 'Aufbau: Verwaltungsseite ist im Host-Layout gerendert');
        $this->assertSame(1, substr_count($html, 'HOSTJS'), 'Inhalt der Host-Section js bleibt in der Seite');
    }

    // Randfall 6
    #[DataProvider('seiten')]
    public function test_randfall6_seite_mit_skripten_behaelt_ihre_skripte_neben_der_host_section_js(string $seite): void
    {
        $this->layout('hostsection');

        $this->assertSkriptGenauEinmal($seite);
    }

    // Randfall 7
    public function test_randfall7_scheitert_die_nachbearbeitung_bleibt_die_seite_vollstaendig_und_nie_leer(): void
    {
        $this->layout('beides');

        // Gegenprobe: mit normalem Limit steht das Skript bei „beides“ genau einmal da (die Nachbearbeitung greift);
        // der Lauf kompiliert und cacht dabei die Views.
        $normal = $this->seite('permission.index');
        $this->assertSame(1, substr_count($normal, '.DataTable('), 'Aufbau: Doppelausgabe wird entfernt');

        $this->app['view']->flushState();
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '400');
        try {
            preg_match('/<a>.*?<\/a>/s', '<a>'.str_repeat('x', 5000).'</a>');
            $this->assertSame(PREG_BACKTRACK_LIMIT_ERROR, preg_last_error(), 'Aufbau: reguläre Ausdrücke über die Seite scheitern');

            $html = (string) $this->actingAs($this->admin)->get($this->url('permission.index'))->assertOk()->getContent();
        } finally {
            ini_restore('pcre.jit');
            ini_restore('pcre.backtrack_limit');
        }

        $this->assertNotSame('', trim($html), 'Seite ist nicht leer');
        $this->assertStringContainsString('<!--HOSTLAYOUT-->', $html, 'Host-Layout ist gerendert');
        $this->assertStringContainsString('<h1', $html, 'Verwaltungsseite ist vollständig');
        $this->assertStringContainsString('</html>', $html);
        $this->assertGreaterThanOrEqual(1, substr_count($html, '.DataTable('), 'Skript der Seite steht in der Seite');
    }

    // ================================================================== AK4 — Sprachdatei

    // AK4
    #[DataProvider('listen')]
    public function test_ak4_sprachdatei_url_im_quelltext_hat_kein_leerzeichen(string $seite): void
    {
        $this->layout('yield');

        $html = $this->seite($seite);

        $this->assertStringContainsString('vendor/laravelkit/DataTable_DE.json"', $html);
        $this->assertStringNotContainsString('DataTable_DE.json ', $html);
    }

    // AK4
    #[DataProvider('listen')]
    public function test_ak4_sprachdatei_url_der_initialisierung_endet_auf_den_dateinamen(string $seite): void
    {
        $this->layout('stack');

        $lauf = $this->skriptLaufen($seite);

        $this->assertNull($lauf['fehler']);
        $this->assertCount(1, $lauf['optionen'], 'Aufbau: Initialisierung ist gelaufen');
        $this->assertStringEndsWith('vendor/laravelkit/DataTable_DE.json', $lauf['optionen'][0]['language']['url']);
    }

    // ================================================================== Randfall 3 — role.js / permission.js

    // Randfall 3
    // permission.js gehört nicht dazu: es ruft `$` auf oberster Ebene auf (Spec-Lücke, im Bericht gemeldet).
    public function test_randfall3_role_js_nutzt_kein_jquery_auf_oberster_ebene(): void
    {
        $datei = 'role.js';
        $text = (string) file_get_contents(__DIR__.'/../../src/Resources/assets/js/'.$datei);

        $this->assertNotSame('', $text, 'Aufbau: Datei gelesen');
        $this->assertSame(0, preg_match('/(?<![\w$.])(\$|jQuery)\s*[(.]/', $text), $datei.' ruft kein `$`/`jQuery` auf');
    }

    // ================================================================== AK5 — README

    private function readmeAbschnitt(): string
    {
        $readme = (string) file_get_contents(__DIR__.'/../../README.md');
        $this->assertSame(1, preg_match('/^#### Verwaltungsseiten[^\n]*\n(.*?)(?=^#{1,4} )/ms', $readme, $treffer), 'Abschnitt „Verwaltungsseiten“ in der README');

        return $treffer[1];
    }

    // AK5
    public function test_ak5_readme_nennt_den_stack_scripts(): void
    {
        $abschnitt = $this->readmeAbschnitt();

        $this->assertMatchesRegularExpression('/`scripts`|@stack\(\'scripts\'\)/', $abschnitt);
    }

    // AK5
    public function test_ak5_readme_nennt_die_section_js(): void
    {
        $abschnitt = $this->readmeAbschnitt();

        $this->assertMatchesRegularExpression('/`js`|@yield\(\'js\'\)/', $abschnitt);
    }

    // AK5
    public function test_ak5_readme_sagt_dass_ein_nachstarter_im_host_layout_nicht_mehr_noetig_ist(): void
    {
        $abschnitt = $this->readmeAbschnitt();

        $this->assertMatchesRegularExpression('/Nachstarter[^\n]*(nicht mehr n(ö|oe)tig|entfällt|kein)|(nicht mehr n(ö|oe)tig|kein)[^\n]*Nachstarter/iu', $abschnitt);
    }
}
