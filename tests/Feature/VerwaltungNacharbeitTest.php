<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Http\Controllers\AuthCssController;
use ITHilbert\UserAuth\Tests\Support\VerwaltungSeitenHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Nacharbeit aus den Review-Funden (kern-umstellung AK12 bis AK14, T5): Dialogbreite und Kartenrand in der CSS,
 * Asset-Routen ohne Session, Tabellen im Rahmen mit Überlauf.
 */
final class VerwaltungNacharbeitTest extends TestCase
{
    use RefreshDatabase;
    use VerwaltungSeitenHilfe;

    private function css(): string
    {
        $pfad = dirname(__DIR__, 2).'/src/Resources/assets/css/userauth-verwaltung.css';
        $this->assertFileExists($pfad);

        return (string) file_get_contents($pfad);
    }

    /**
     * Regeln der (minifizierten, evtl. verschachtelten) CSS in Dateireihenfolge.
     *
     * @return list<array{kontext: list<string>, selektoren: list<string>, body: string}>
     */
    private function regeln(string $css): array
    {
        $regeln = [];
        $stack = [];
        $puffer = '';
        $laenge = strlen($css);
        for ($i = 0; $i < $laenge; $i++) {
            $c = $css[$i];
            if ($c === '{') {
                $kopf = trim($puffer);
                $puffer = '';
                if (str_starts_with($kopf, '@')) {
                    $stack[] = $kopf;

                    continue;
                }
                $tiefe = 1;
                $j = $i + 1;
                while ($j < $laenge && $tiefe > 0) {
                    $tiefe += $css[$j] === '{' ? 1 : ($css[$j] === '}' ? -1 : 0);
                    $j++;
                }
                $regeln[] = [
                    'kontext' => $stack,
                    'selektoren' => array_map('trim', explode(',', $kopf)),
                    'body' => substr($css, $i + 1, $j - $i - 2),
                ];
                $i = $j - 1;
            } elseif ($c === '}') {
                array_pop($stack);
                $puffer = '';
            } elseif ($c === ';') {
                $puffer = '';
            } else {
                $puffer .= $c;
            }
        }

        return $regeln;
    }

    /**
     * @return list<string> Werte der Eigenschaft (Name als Regex, z. B. `overflow[\w-]*`) im Regelrumpf
     */
    private function werte(string $body, string $eigenschaft): array
    {
        preg_match_all('/(?:^|[;{])\s*('.$eigenschaft.')\s*:\s*([^;}]*)/', $body, $treffer, PREG_SET_ORDER);

        return array_map(fn (array $t): string => $t[1].':'.trim($t[2]), $treffer);
    }

    private function ohneLayer(array $regel): bool
    {
        foreach ($regel['kontext'] as $at) {
            if (str_starts_with($at, '@layer')) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ AK12

    // AK12
    public function test_ak12_dialog_hat_ohne_layer_eine_breitenbegrenzung_unter_100vw(): void
    {
        $regeln = $this->regeln($this->css());

        // Gegenprobe: die Kit-Basisregel (ohne Layer) begrenzt auf 100vw, davor greift der Parser sie
        $letzte = null;
        $anzahl = 0;
        foreach ($regeln as $regel) {
            if (in_array('.kit-dialog__content', $regel['selektoren'], true) && $this->ohneLayer($regel)) {
                foreach ($this->werte($regel['body'], 'max-width') as $wert) {
                    $letzte = $wert;
                    $anzahl++;
                }
            }
        }
        $this->assertGreaterThan(0, $anzahl, 'Aufbau: ungeschichtete max-width-Regel für .kit-dialog__content gefunden');
        $this->assertNotNull($letzte);
        $wert = trim(substr($letzte, strlen('max-width:')));

        $this->assertNotSame('100vw', $wert, 'die zuletzt wirksame Regel ohne Layer begrenzt nicht auf 100vw');
        $this->assertGreaterThan(1, $anzahl, 'eigene Regel nach der Kit-Regel (die letzte gewinnt)');
        $this->assertSame(1, preg_match('/^(min\(\s*(\d+(?:\.\d+)?)rem\b|(\d+(?:\.\d+)?)rem$)/', $wert, $m), 'max-width höchstens 32rem, z. B. min(32rem, calc(100vw - 2rem)): '.$wert);
        $this->assertLessThanOrEqual(32.0, (float) ($m[2] !== '' ? $m[2] : $m[3]));
    }

    // AK12
    public function test_ak12_kit_card_body_hat_keine_overflow_regel_nur_dt_container_darf_overflow_x(): void
    {
        $gefunden = 0;
        foreach ($this->regeln($this->css()) as $regel) {
            foreach ($regel['selektoren'] as $selektor) {
                if (! str_starts_with($selektor, '.kit-card__body')) {
                    continue;
                }
                $gefunden++;
                $overflow = $this->werte($regel['body'], 'overflow[\w-]*');
                if ($selektor === '.kit-card__body .dt-container') {
                    foreach ($overflow as $w) {
                        $this->assertStringStartsWith('overflow-x:', $w, 'am dt-container nur overflow-x');
                    }

                    continue;
                }
                $this->assertSame([], $overflow, 'keine overflow-Regel an '.$selektor.' (die Combobox-Liste würde abgeschnitten)');
            }
        }
        $this->assertGreaterThan(0, $gefunden, 'Gegenprobe: Regeln für .kit-card__body sind in der CSS (Parser greift)');
    }

    // ------------------------------------------------------------------ AK13

    /** @return array<string, array{0: string}> */
    public static function assetRouten(): array
    {
        return ['userauth.assets.css' => ['userauth.assets.css'], 'userauth.assets.verwaltung' => ['userauth.assets.verwaltung']];
    }

    // AK13
    #[DataProvider('assetRouten')]
    public function test_ak13_asset_route_antwortet_mit_immutable_ohne_cookie_und_session(string $name): void
    {
        $antwort = $this->get(route($name))->assertOk();

        $cache = (string) $antwort->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=31536000', $cache);
        $this->assertStringContainsString('immutable', $cache);
        $this->assertSame([], $antwort->headers->getCookies(), 'kein Set-Cookie');
        $this->assertFalse($antwort->headers->has('Set-Cookie'));
        $this->assertFalse(session()->isStarted(), 'keine Session gestartet');
    }

    // AK13
    public function test_ak13_andere_paket_routen_laufen_weiter_durch_web(): void
    {
        // Gegenprobe: eine Seite der Gruppe web setzt Cookies (Sitzung), also trennt der Test web von den Assets
        $login = $this->get(route('login'))->assertOk();
        $this->assertNotSame([], $login->headers->getCookies(), 'Login-Seite setzt weiter ein Cookie');

        $pruefte = 0;
        foreach (Route::getRoutes() as $route) {
            $aktion = $route->getAction('controller');
            $controller = is_string($aktion) ? explode('@', $aktion)[0] : '';
            if (! str_starts_with($controller, 'ITHilbert\\UserAuth\\Http\\Controllers\\')) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            if ($controller === AuthCssController::class) {
                $this->assertNotContains('web', $middleware, $route->uri().' läuft ohne web');

                continue;
            }
            $this->assertContains('web', $middleware, $route->uri().' läuft durch web');
            $pruefte++;
        }
        $this->assertGreaterThan(10, $pruefte, 'Aufbau: Routen des Pakets gefunden');
    }

    // ------------------------------------------------------------------ AK14

    /** @return array<string, array{0: string}> */
    public static function formulare(): array
    {
        return ['role.create' => ['role.create'], 'role.edit' => ['role.edit'], 'permission.create' => ['permission.create'], 'permission.edit' => ['permission.edit']];
    }

    // AK14
    #[DataProvider('formulare')]
    public function test_ak14_jede_statische_tabelle_steht_in_einem_rahmen(string $seite): void
    {
        $this->verwaltungAufbauen('adminviews');
        $xp = $this->verwaltungDom($this->verwaltungSeite($seite));

        $tabellen = $xp->query('//table[contains(concat(" ", normalize-space(@class), " "), " userauth-tabelle ")]');
        $this->assertGreaterThan(0, $tabellen->length, 'Aufbau: statische Tabelle gerendert');
        foreach ($tabellen as $tabelle) {
            $rahmen = $tabelle->parentNode;
            $this->assertInstanceOf(DOMElement::class, $rahmen);
            $this->assertSame('div', $rahmen->tagName, 'Tabelle steht direkt in einem div');
            $this->assertContains('userauth-tabelle-rahmen', preg_split('/\s+/', $rahmen->getAttribute('class')) ?: [], 'div.userauth-tabelle-rahmen');
        }
    }

    // AK14
    public function test_ak14_css_setzt_overflow_am_rahmen_und_kein_display_block_an_der_tabelle(): void
    {
        $regeln = $this->regeln($this->css());

        $rahmen = [];
        $tabelle = 0;
        foreach ($regeln as $regel) {
            foreach ($regel['selektoren'] as $selektor) {
                if ($selektor === '.userauth-tabelle-rahmen') {
                    $rahmen = [...$rahmen, ...$this->werte($regel['body'], 'overflow-x')];
                }
                if ($selektor === '.userauth-tabelle' || $selektor === 'table.userauth-tabelle') {
                    $tabelle++;
                    $this->assertSame([], $this->werte($regel['body'], 'display'), 'kein display an table.userauth-tabelle (kein block)');
                    $this->assertSame([], $this->werte($regel['body'], 'overflow[\w-]*'), 'kein overflow an der Tabelle selbst');
                }
            }
        }
        $this->assertGreaterThan(0, $tabelle, 'Gegenprobe: Regel für table.userauth-tabelle gefunden (Parser greift)');
        $this->assertContains('overflow-x:auto', $rahmen, 'Rahmen setzt overflow-x: auto');
    }
}
