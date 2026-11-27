<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use ITHilbert\LaravelKit\LaravelKitServiceProvider;
use ITHilbert\UserAuth\Tests\Support\VerwaltungSeitenHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * Lösch-Dialog der drei Listen als `<x-kit::dialog-delete>` mit Modul-Skript und Asset-Routen (loesch-dialog-kit-component, #2449).
 * Das Verhalten im Browser prüft tests/js/loeschen-dialog.test.mjs gegen das Kit-Modul.
 */
final class LoeschDialogKitComponentTest extends TestCase
{
    use RefreshDatabase;
    use VerwaltungSeitenHilfe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verwaltungAufbauen('adminviews');
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> Liste, Lösch-Route, Titel */
    public static function listen(): array
    {
        return [
            'user' => ['user', 'user.delete', 'Benutzer löschen'],
            'role' => ['role', 'role.delete', 'Rolle löschen'],
            'permission' => ['permission', 'permission.delete', 'Recht löschen'],
        ];
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function kitDateien(): array
    {
        return [
            'loesch-dialog' => ['userauth.assets.kit.loesch-dialog', 'loesch-dialog.js'],
            'loeschen' => ['userauth.assets.kit.loeschen', 'loeschen.js'],
        ];
    }

    private function paket(string $datei): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$datei);
    }

    private function kitJs(string $datei): string
    {
        $kit = dirname((string) (new ReflectionClass(LaravelKitServiceProvider::class))->getFileName(), 2);

        return (string) file_get_contents($kit.'/js/'.$datei);
    }

    private function klasse(string $klasse): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$klasse.' ")';
    }

    /** Alle `<script>`-Blöcke der Seite als [Attribute, Inhalt]. @return list<array{0: string, 1: string}> */
    private function skripte(string $html): array
    {
        preg_match_all('#<script([^>]*)>(.*?)</script>#is', $html, $treffer, PREG_SET_ORDER);

        return array_map(fn (array $t) => [$t[1], $t[2]], $treffer);
    }

    // AK1
    #[DataProvider('listen')]
    public function test_ak1_liste_enthaelt_den_dialog_der_component_auf_deutsch(string $liste, string $route, string $titel): void
    {
        app()->setLocale('de');
        $xp = $this->verwaltungDom($this->verwaltungSeite($liste));

        $wurzel = $xp->query('//*[@data-kit-delete-dialog]');
        $this->assertSame(1, $wurzel->length, 'genau eine Dialog-Wurzel');
        $this->assertSame(route($route, 0), $wurzel->item(0)->getAttribute('data-route'), 'Lösch-Route mit ID 0');

        $ueberschrift = $xp->query('.//*['.$this->klasse('kit-dialog__title').']', $wurzel->item(0));
        $this->assertSame($titel, trim($ueberschrift->item(0)?->textContent ?? ''), 'bisheriger Titel');
        $text = trim($xp->query('.//p', $wurzel->item(0))->item(0)?->textContent ?? '');
        $this->assertNotSame('', $text, 'Text vorhanden');
        $this->assertNotSame(__('laravelkit::dialog.body'), $text, 'der Text der Liste, nicht die Vorgabe des Kits');

        $abbrechen = $xp->query('.//button[@data-kit-delete-abbrechen]', $wurzel->item(0));
        $loeschen = $xp->query('.//button[@data-kit-delete-bestaetigen]', $wurzel->item(0));
        $this->assertSame('Abbrechen', trim($abbrechen->item(0)?->textContent ?? ''));
        $this->assertSame('Löschen', trim($loeschen->item(0)?->textContent ?? ''));

        $form = $xp->query('//form[@id="formDelete"]');
        $this->assertSame(1, $form->length, 'genau ein Formular #formDelete');
        $this->assertSame('post', strtolower($form->item(0)->getAttribute('method')));
        $this->assertSame('DELETE', $xp->query('.//input[@name="_method"]', $form->item(0))->item(0)?->getAttribute('value'));
        $this->assertNotSame('', (string) $xp->query('.//input[@name="_token"]', $form->item(0))->item(0)?->getAttribute('value'));
    }

    // AK1
    #[DataProvider('listen')]
    public function test_ak1_knoepfe_des_dialogs_sind_auf_englisch_cancel_und_delete(string $liste, string ...$_): void
    {
        app()->setLocale('en');
        $xp = $this->verwaltungDom($this->verwaltungSeite($liste));

        $wurzel = $xp->query('//*[@data-kit-delete-dialog][@data-route]');
        $this->assertSame(1, $wurzel->length, 'Aufbau: Dialog der Component ist da');
        $this->assertSame('Cancel', trim($xp->query('.//button[@data-kit-delete-abbrechen]', $wurzel->item(0))->item(0)?->textContent ?? ''));
        $this->assertSame('Delete', trim($xp->query('.//button[@data-kit-delete-bestaetigen]', $wurzel->item(0))->item(0)?->textContent ?? ''));
    }

    // AK2
    #[DataProvider('listen')]
    public function test_ak2_seite_hat_ein_modul_skript_und_keinen_inline_handler(string $liste, string ...$_): void
    {
        $skripte = $this->skripte($this->verwaltungSeite($liste));

        $module = array_values(array_filter($skripte, fn (array $s) => preg_match('/type\s*=\s*["\']?module/i', $s[0]) === 1 && str_contains($s[1], 'initLoeschDialog')));
        $this->assertCount(1, $module, 'genau ein Modul-Skript mit initLoeschDialog');
        $code = str_replace('\\/', '/', $module[0][1]);
        $this->assertStringContainsString('userauth/assets/kit/loesch-dialog.js', $code, 'importiert aus der Asset-Route');
        $this->assertMatchesRegularExpression('/initLoeschDialog\s*\(/', $code, 'ruft initLoeschDialog auf');

        foreach ($skripte as [, $inhalt]) {
            // einzelwort: Wort kommt nur im alten Inline-Skript vor (Gegenprobe: Modul-Skript bzw. Component vorhanden)
            $this->assertStringNotContainsString('routeMitId', $inhalt, 'kein Inline-Skript des Lösch-Dialogs');
            $this->assertStringNotContainsString('data-kit-delete-bestaetigen', $inhalt, 'kein Klick-Handler im Seitenquelltext');
        }
    }

    // AK3
    #[DataProvider('kitDateien')]
    public function test_ak3_asset_route_liefert_die_kit_datei_ohne_session_und_mit_kurzem_cache(string $routenName, string $datei): void
    {
        $antwort = $this->get(route($routenName))->assertOk();

        $this->assertStringStartsWith('text/javascript', (string) $antwort->headers->get('Content-Type'));
        $this->assertSame($this->kitJs($datei), $antwort->streamedContent() ?: $antwort->getContent());
        $this->assertSame([], $antwort->headers->getCookies(), 'kein Session-Cookie');
        $cache = (string) $antwort->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=300', $cache);
        // einzelwort: Cache-Control eines Assets enthält das Wort nur bei immutable (Gegenprobe: max-age=300 geprüft)
        $this->assertStringNotContainsString('immutable', $cache);
    }

    // AK4
    public function test_ak4_andere_dateien_und_pfade_mit_punkten_geben_404(): void
    {
        $this->get('/userauth/assets/kit/loesch-dialog.js')->assertOk();   // Gegenprobe: die Whitelist-Route antwortet

        foreach (['mount-inseln.js', 'tabs.js', 'combobox.js', '..%2Floesch-dialog.js', '..%2F..%2Fcomposer.json', '%2e%2e%2ftabs.js'] as $datei) {
            $this->get('/userauth/assets/kit/'.$datei)->assertNotFound();
        }
        $this->get('/userauth/assets/kit/../kit/tabs.js')->assertNotFound();
    }

    // AK5
    public function test_ak5_partial_hat_kein_eigenes_formular_und_keinen_klick_handler(): void
    {
        $partial = $this->paket('src/Resources/views/partials/loeschen.blade.php');

        $this->assertStringContainsString('dialog-delete', $partial, 'Aufbau: Partial nutzt die Component');
        $this->assertStringNotContainsString('<form id="formDelete"', $partial);
        // einzelwort: Wort kommt nur im alten Inline-Skript vor (Gegenprobe: Modul-Skript bzw. Component vorhanden)
        $this->assertStringNotContainsString('routeMitId', $partial, 'kein Klick-Handler im Stack');
        // einzelwort: Wort kommt nur im alten Inline-Skript vor (Gegenprobe: Modul-Skript bzw. Component vorhanden)
        $this->assertStringNotContainsString('addEventListener', $partial, 'kein Klick-Handler im Stack');
    }

    /** CSS ohne Leerraum; Rumpf der ersten Regel mit genau diesem Selektor. */
    private function regel(string $css, string $selektor): string
    {
        $css = preg_replace('/\s+/', '', $css) ?? '';
        $this->assertSame(1, preg_match('/(?:^|[}{]|\*\/)'.preg_quote($selektor, '/').'\{([^}]*)\}/', $css, $treffer), 'Regel '.$selektor);

        return $treffer[1] ?? '';
    }

    /** @return array<string, array{0: string}> */
    public static function cssDateien(): array
    {
        return [
            'vorkompiliert' => ['src/Resources/assets/css/userauth-verwaltung.css'],
            'quelle' => ['resources-src/verwaltung.css'],
        ];
    }

    // AK6
    #[DataProvider('cssDateien')]
    public function test_ak6_css_hat_die_feste_dialog_ebene_der_kit_component(string $datei): void
    {
        $css = $this->paket($datei);

        $this->assertStringContainsString('display:none', $this->regel($css, '.kit-dialog[hidden]'));
        $offen = $this->regel($css, '.kit-dialog:not([hidden])');
        $this->assertStringContainsString('position:fixed', $offen);
        $this->assertStringContainsString('inset:0', $offen);
        $this->assertStringContainsString('display:flex', $offen, 'Inhalt zentriert');
        $this->assertStringContainsString('32rem', $this->regel($css, '.kit-dialog__content'), 'Dialogbreite bleibt begrenzt');
    }

    // AK7
    public function test_ak7_composer_readme_und_changelog_nennen_component_und_version(): void
    {
        $composer = json_decode($this->paket('composer.json'), true);
        $this->assertSame('^1.14', $composer['require']['ithilbert/laravel-kit'] ?? null);

        $readme = $this->paket('README.md');
        $this->assertStringContainsString('<x-kit::dialog-delete>', $readme);
        $this->assertStringContainsString('userauth.assets.kit.loesch-dialog', $readme);
        $this->assertStringContainsString('userauth.assets.kit.loeschen', $readme);
        $this->assertMatchesRegularExpression('/Sprachdatei/u', $readme, 'Texte aus den Sprachdateien des Kits');
        foreach (['- **Kit-Components und Skripte:**', '- **Lösch-Buttons:**', '3. **Lösch-Dialog'] as $anfang) {
            $this->assertSame(1, preg_match('/^'.preg_quote($anfang, '/').'[^\n]*/mu', $readme, $zeile), 'README-Zeile '.$anfang);
            // einzelwort: Wort steht in der Zeile nur im alten Inline-Text (Gegenprobe: Zeile gefunden)
            $this->assertStringNotContainsString('Inline-Skript', $zeile[0], $anfang.' beschreibt kein Inline-Skript mehr');
        }

        $this->assertSame(1, preg_match('/^## 4\.7\.0\b.*?(?=^## |\z)/ms', $this->paket('CHANGELOG.md'), $eintrag), 'Abschnitt 4.7.0');
        $this->assertStringContainsString('#2449', $eintrag[0]);
    }
}
