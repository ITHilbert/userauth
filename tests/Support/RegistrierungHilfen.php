<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;

/**
 * Gemeinsame Hilfen der Registrierungs-Tests (userauth-registrierung, Dashboard #255).
 *
 * Routenpfade und -namen außer `/register` wählt die Umsetzung. Die Tests finden sie über beobachtbares Verhalten:
 * den Bestätigungslink aus der Mail und die Routen, die zur Registrierung gehören (siehe `registrierungsRouten()`).
 *
 * Config-Schalter: Routen werden beim Booten registriert. Zustände mit anderem Schalter starten die App neu
 * (`neuStarten()`), die Datenbank (SQLite :memory:) bleibt dabei erhalten.
 *
 * Annahme an die Bestätigungsmail: Sie enthält genau einen Link (signierte URL, Parameter `signature=`), der aus
 * `APP_URL` gebildet ist. Alle signierten Links der Mail zeigen auf dieselbe URL.
 */
trait RegistrierungHilfen
{
    use NachrichtenAbfangen;

    public const NICHT_GESETZT = '__nicht_gesetzt__';

    protected const PASSWORT = 'Sicheres-Passwort-2026!';

    /**
     * Überschreibungen der Registrierungs-Config für den nächsten App-Start. Schlüssel: enabled, role, ttl;
     * `paketstandard` = true: nichts setzen, es gilt die Vorgabe der Paket-Config.
     *
     * @var array<string, mixed>
     */
    protected static array $konfiguration = [];

    private int $ipZaehler = 10;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        if (static::$konfiguration['paketstandard'] ?? false) {
            return;
        }

        $werte = array_merge(
            ['enabled' => true, 'role' => 'user', 'ttl' => 60],
            static::$konfiguration,
        );
        $schluessel = ['enabled' => 'enabled', 'role' => 'role', 'ttl' => 'verify_ttl_minutes'];
        $registrierung = [];
        foreach ($schluessel as $kurz => $lang) {
            if ($werte[$kurz] !== self::NICHT_GESETZT) {
                $registrierung[$lang] = $werte[$kurz];
            }
        }

        // Die Paket-Config wird flach gemergt: die ganze Registrierungs-Gruppe als Einheit setzen.
        $app['config']->set('userauth.registration', $registrierung);
    }

    protected function tearDown(): void
    {
        static::$konfiguration = [];

        parent::tearDown();
    }

    protected function grundeinstellung(bool $mailAbfangen = true): void
    {
        if ($mailAbfangen) {
            $this->nachrichtenAbfangen();
        }
        app()->setLocale('de');
        View::addLocation(dirname(__DIR__).'/Fixtures/views');
        config()->set('userauth.redirect_after_login', '/');
        config()->set('userauth.two_factor_enabled', false);
        config()->set('userauth.audit_log_enabled', false);
        config()->set('userauth.password_policy.enabled', false);
        config()->set('userauth.theme', 'tailwind');
    }

    /**
     * Startet die App mit anderer Registrierungs-Config neu; Datenbank bleibt, Laufzeit-Einstellungen werden neu gesetzt.
     *
     * @param  array<string, mixed>  $konfiguration
     */
    protected function neuStarten(array $konfiguration, bool $mailAbfangen = true): void
    {
        $pdo = DB::connection()->getPdo();
        static::$konfiguration = $konfiguration;

        $this->refreshApplication();

        DB::connection()->setPdo($pdo);
        DB::connection()->setReadPdo($pdo);
        $this->grundeinstellung($mailAbfangen);
    }

    protected function rollenAnlegen(): void
    {
        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Entwickler', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Administrator', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 3, 'role' => 'user', 'role_display' => 'Benutzer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
    }

    protected function auditTabelleBereitstellen(): void
    {
        if (! Schema::hasTable('user_auth_audit_logs')) {
            Schema::create('user_auth_audit_logs', function ($t): void {
                $t->bigIncrements('id');
                $t->bigInteger('user_id')->nullable();
                $t->string('email')->nullable();
                $t->string('event');
                $t->string('ip_address')->nullable();
                $t->string('user_agent')->nullable();
                $t->timestamps();
            });
        }
    }

    /**
     * @return array<string, string>
     */
    protected function gueltigeDaten(string $email = 'kunde@example.test'): array
    {
        return [
            'name' => 'Erika Kunde',
            'email' => $email,
            'password' => self::PASSWORT,
            'password_confirmation' => self::PASSWORT,
        ];
    }

    /**
     * POST /register; ohne IP-Angabe bekommt jeder Aufruf eine andere IP (das Rate-Limit je IP greift sonst bei vielen Konten).
     *
     * @param  array<string, mixed>  $felder  überschreibt/ergänzt die gültigen Daten
     * @param  list<string>  $ohne  Felder, die gar nicht gesendet werden
     */
    protected function registrieren(array $felder = [], ?string $ip = null, array $ohne = []): TestResponse
    {
        $daten = array_merge($this->gueltigeDaten(), $felder);
        foreach ($ohne as $feld) {
            unset($daten[$feld]);
        }

        $ip ??= '198.51.100.'.(++$this->ipZaehler);

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->from('/register')->post('/register', $daten);
    }

    protected function anmelden(string $email, string $passwort = self::PASSWORT): TestResponse
    {
        return $this->from('/login')->post('/login', ['email' => $email, 'password' => $passwort]);
    }

    /**
     * Zeilen der Tabelle users zu einer E-Mail, Groß-/Kleinschreibung egal, inklusive soft-gelöschter.
     *
     * @return list<object>
     */
    protected function konten(string $email): array
    {
        return DB::table('users')->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->get()->all();
    }

    /**
     * Die eine Zeile des Kontos; schlägt mit klarer Meldung fehl, wenn es kein Konto gibt.
     */
    protected function konto(string $email): object
    {
        $konten = $this->konten($email);
        $this->assertCount(1, $konten, "Erwartet genau ein Konto für {$email}.");

        return $konten[0];
    }

    protected function kontoId(string $email): int
    {
        $konten = $this->konten($email);
        $this->assertCount(1, $konten, "Erwartet genau ein Konto für {$email}.");

        return (int) $konten[0]->id;
    }

    /**
     * Link aus der letzten Mail an die exakte Adresse: signierte URL (`signature=`).
     */
    protected function bestaetigungsLink(string $email): string
    {
        $nachrichten = $this->nachrichtenAn($email);
        $this->assertNotEmpty($nachrichten, "Es wurde keine Mail an {$email} versendet.");

        return $this->linkAusMail(end($nachrichten));
    }

    /**
     * @param  array{an: list<string>, html: string, text: string}  $nachricht
     */
    protected function linkAusMail(array $nachricht): string
    {
        $this->assertGreaterThan(0, preg_match_all('~https?://[^\s"\'<>]*signature=[^\s"\'<>]+~', $nachricht['html'], $treffer), 'Die Mail enthält keinen signierten Link.');
        $links = array_values(array_unique(array_map('html_entity_decode', $treffer[0])));
        $this->assertCount(1, $links, 'Die Mail enthält mehr als einen signierten Link.');

        return $links[0];
    }

    /**
     * Registriert über die Route und liefert den Link der Bestätigungsmail.
     */
    protected function registriertMitLink(string $email = 'kunde@example.test'): string
    {
        $this->registrieren(['email' => $email]);

        return $this->bestaetigungsLink($email);
    }

    /**
     * Routen, die zur Registrierung gehören (außer /register): Bestätigung und „Link erneut anfordern“.
     * Gefunden über Namen/Pfad (regist|verif|bestätig|resend|erneut), ohne die Zwei-Faktor- und Social-Routen.
     *
     * @return list<LaravelRoute>
     */
    protected function registrierungsRouten(): array
    {
        $treffer = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            if (in_array($name, ['verify.index', 'verify.store', 'verify.resend'], true) || str_starts_with($name, 'userauth.social')) {
                continue;
            }
            if ($route->uri() === 'register') {
                continue;
            }
            if (preg_match('~regist|verif|best[aä]tig|resend|erneut~i', $route->uri().' '.$name) === 1) {
                $treffer[] = $route;
            }
        }

        return $treffer;
    }

    /**
     * Route „Link erneut anfordern“: POST ohne Pfadparameter.
     */
    protected function erneutAnfordernRoute(): LaravelRoute
    {
        foreach ($this->registrierungsRouten() as $route) {
            if (in_array('POST', $route->methods(), true) && ! str_contains($route->uri(), '{')) {
                return $route;
            }
        }

        $this->fail('Es gibt keine POST-Route „Link erneut anfordern“ (Pfad/Name mit regist|verif|bestätig|resend|erneut, ohne Pfadparameter).');
    }

    protected function erneutAnfordern(string $email, ?string $ip = '203.0.113.50'): TestResponse
    {
        $route = $this->erneutAnfordernRoute();
        $client = $ip === null ? $this : $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $client->from('/login')->post('/'.ltrim($route->uri(), '/'), ['email' => $email]);
    }

    /**
     * Seiteninhalt ohne Werte, die je Sitzung wechseln (CSRF-Token).
     */
    protected function ohneToken(string $html): string
    {
        $html = (string) preg_replace('~(name="_token"[^>]*value=")[^"]*~', '$1', $html);
        $html = (string) preg_replace('~(name="csrf-token"[^>]*content=")[^"]*~', '$1', $html);

        return (string) preg_replace('~(content=")[^"]*("[^>]*name="csrf-token")~', '$1$2', $html);
    }

    /**
     * Antwort eines Aufrufs als vergleichbarer Satz: Status, Ziel und Inhalt der Folgeseite (ohne Token).
     *
     * @return array{status: int, ziel: ?string, seite: ?string}
     */
    protected function antwortMerkmale(TestResponse $antwort): array
    {
        $ziel = $antwort->headers->get('Location');
        $seite = $ziel === null ? $this->ohneToken((string) $antwort->getContent()) : $this->ohneToken((string) $this->get($ziel)->getContent());

        return ['status' => $antwort->getStatusCode(), 'ziel' => $ziel, 'seite' => $seite];
    }

    protected function seitentext(string $html): string
    {
        $html = (string) preg_replace('~<(script|style)\b.*?</\1>~si', ' ', $html);

        return trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($html))));
    }

    /**
     * Alle Zeilen aller Tabellen: Beleg, dass ein Aufruf nichts verändert hat.
     *
     * @return array<string, list<object>>
     */
    protected function alleTabellen(): array
    {
        $inhalte = [];
        foreach (DB::select("select name from sqlite_master where type = 'table'") as $tabelle) {
            $inhalte[$tabelle->name] = DB::table($tabelle->name)->get()->all();
        }

        return $inhalte;
    }

    /**
     * Felder eines Formulars wie der Browser sie sendet: alle mit name, die nicht disabled sind; letzter Wert gewinnt.
     *
     * @return array<string, string>
     */
    protected function formularFelder(string $html, string $actionEndetMit): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($dom);

        $felder = [];
        foreach ($xpath->query('//form') as $formular) {
            $pfad = (string) parse_url((string) $formular->getAttribute('action'), PHP_URL_PATH);
            if (! str_ends_with($pfad, $actionEndetMit)) {
                continue;
            }
            foreach ($xpath->query('.//input[@name] | .//textarea[@name] | .//select[@name]', $formular) as $feld) {
                if ($feld->hasAttribute('disabled')) {
                    continue;
                }
                $typ = strtolower((string) $feld->getAttribute('type'));
                if (in_array($typ, ['submit', 'button', 'image', 'file'], true)) {
                    continue;
                }
                if (in_array($typ, ['checkbox', 'radio'], true) && ! $feld->hasAttribute('checked')) {
                    continue;
                }
                $felder[$feld->getAttribute('name')] = $feld->nodeName === 'textarea' ? $feld->textContent : (string) $feld->getAttribute('value');
            }
        }

        return $felder;
    }

    /**
     * Pfade und Ziele aller Verweise und Formulare einer Seite (href und action).
     *
     * @return list<string>
     */
    protected function verweiseDerSeite(string $html): array
    {
        preg_match_all('~(?:href|action)\s*=\s*"([^"]*)"~i', $html, $treffer);

        return array_map(static fn (string $ziel): string => html_entity_decode($ziel), $treffer[1]);
    }

    /**
     * Zeigt die Seite einen Weg zu „Link erneut anfordern“ (Verweis oder Formular auf eine der Routen)?
     */
    protected function hatWegZumErneutenAnfordern(string $html): bool
    {
        $pfade = [];
        foreach ($this->registrierungsRouten() as $route) {
            if (! str_contains($route->uri(), '{')) {
                $pfade[] = '/'.ltrim($route->uri(), '/');
            }
        }

        foreach ($this->verweiseDerSeite($html) as $ziel) {
            $pfad = (string) parse_url($ziel, PHP_URL_PATH);
            if (in_array($pfad, $pfade, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Link verändern, so wie es ein Angreifer täte.
     */
    protected function linkVeraendern(string $link, string $art): string
    {
        $teile = parse_url($link);
        $this->assertIsArray($teile);
        parse_str($teile['query'] ?? '', $abfrage);
        $pfad = $teile['path'] ?? '';

        switch ($art) {
            case 'signatur_veraendert':
                $abfrage['signature'] = strrev((string) $abfrage['signature']);
                break;
            case 'signatur_entfernt':
                unset($abfrage['signature']);
                break;
            case 'ablaufzeit_veraendert':
                $abfrage['expires'] = (string) ((int) ($abfrage['expires'] ?? time()) + 86400);
                break;
            case 'pfad_veraendert':
                $letztes = substr($pfad, -1);
                $pfad = substr($pfad, 0, -1).($letztes === 'a' ? 'b' : 'a');
                break;
            case 'konto_id_ausgetauscht':
                $this->assertSame(1, preg_match('~/(\d+)(/|$)~', $pfad), 'Der Link enthält keine Konto-ID im Pfad.');
                $pfad = (string) preg_replace_callback('~/(\d+)(/|$)~', static fn (array $t): string => '/'.((int) $t[1] + 1).$t[2], $pfad, 1);
                break;
            default:
                $this->fail("Unbekannte Veränderung {$art}");
        }

        return ($teile['scheme'] ?? 'http').'://'.($teile['host'] ?? 'localhost').(isset($teile['port']) ? ':'.$teile['port'] : '').$pfad
            .($abfrage === [] ? '' : '?'.http_build_query($abfrage));
    }
}
