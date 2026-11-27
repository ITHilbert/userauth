<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ITHilbert\UserAuth\Tests\Support\VerwaltungSeitenHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Optik der DataTables in der Verwaltung (datatables-optik, #2369): Layout-Option, Neu-Button im Listenkopf,
 * Zahlenspalte, CSS ohne Hex-Farben, unveränderte Grundoptionen. Das Inline-Skript der Seite läuft in Node
 * gegen einen DataTable-Stand mit wählbarer Version.
 */
final class DatatablesOptikTest extends TestCase
{
    use RefreshDatabase;
    use VerwaltungSeitenHilfe;

    private const CREATE_ROUTEN = ['user' => 'user.create', 'role' => 'role.create', 'permission' => 'permission.create'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->verwaltungAufbauen('skripteviews/beides');
    }

    /** @return array<string, array{0: string}> */
    public static function listen(): array
    {
        return ['Benutzer' => ['user'], 'Rollen' => ['role'], 'Rechte' => ['permission']];
    }

    /** @return array<string, array{0: string}> */
    public static function cssDateien(): array
    {
        return [
            'Quelle verwaltung.css' => ['resources-src/verwaltung.css'],
            'kompilierte userauth-verwaltung.css' => ['src/Resources/assets/css/userauth-verwaltung.css'],
        ];
    }

    // ------------------------------------------------------------------ Hilfen

    /**
     * Optionen des `new DataTable(...)`-Aufrufs der Seite, ausgeführt gegen DataTables der angegebenen Version.
     *
     * @return array<string, mixed>
     */
    private function optionen(string $seite, string $version): array
    {
        $html = $this->verwaltungSeite($seite);
        $this->assertStringContainsString('<h1', $html, 'Aufbau: Verwaltungsseite ist gerendert');

        $code = null;
        preg_match_all('#<script(?![^>]*\ssrc=)[^>]*>(.*?)</script>#is', $html, $treffer);
        foreach ($treffer[1] as $skript) {
            if (str_contains($skript, 'new DataTable(')) {
                $code = $skript;
            }
        }
        $this->assertNotNull($code, 'Inline-Skript mit der DataTable-Initialisierung steht in der Seite');

        $datei = tempnam(sys_get_temp_dir(), 'userauth-dt-');
        $this->assertNotFalse($datei);
        try {
            file_put_contents($datei, $code);
            $ausgabe = shell_exec('node '.escapeshellarg(__DIR__.'/../Fixtures/datatables-version-lauf.mjs').' '.escapeshellarg($datei).' '.escapeshellarg($version).' 2>&1');
        } finally {
            @unlink($datei);
        }

        $lauf = json_decode((string) $ausgabe, true);
        $this->assertIsArray($lauf, 'Lauf lieferte JSON, war: '.$ausgabe);
        $this->assertNull($lauf['fehler'], 'Skript läuft ohne Fehler');
        $this->assertCount(1, $lauf['optionen'], 'DataTable genau einmal initialisiert');

        return $lauf['optionen'][0];
    }

    /**
     * @return list<array{selektoren: string, body: string}> Regeln ohne Kommentare (innerste Blöcke, at-Regeln wie @layer zählen nicht)
     */
    private function cssRegeln(string $datei): array
    {
        $pfad = dirname(__DIR__, 2).'/'.$datei;
        $this->assertFileExists($pfad);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($pfad));

        preg_match_all('/([^{};]+)\{([^{}]*)\}/', $css, $treffer, PREG_SET_ORDER);
        $regeln = [];
        foreach ($treffer as [, $kopf, $body]) {
            $regeln[] = ['selektoren' => trim($kopf), 'body' => $body];
        }

        return $regeln;
    }

    /** @return list<array{selektoren: string, body: string}> */
    private function regelnMit(string $datei, string $muster): array
    {
        return array_values(array_filter($this->cssRegeln($datei), fn (array $r): bool => preg_match($muster, $r['selektoren']) === 1));
    }

    // ------------------------------------------------------------------ AK1

    // AK1 browser: Darstellung der Steuerleiste zeigt erst der Browser; hier angenähert über die an DataTables übergebenen Optionen
    #[DataProvider('listen')]
    public function test_ak1_angenaehert_datatables_2_bekommt_layout_mit_suche_links_und_laenge_rechts(string $seite): void
    {
        $opt = $this->optionen($seite, '2.1.8');

        $this->assertSame('search', $opt['layout']['topStart'] ?? null);
        $this->assertSame('pageLength', $opt['layout']['topEnd'] ?? null);
    }

    // AK1 angenähert: DataTables 1.x nur als Optionsstand geprüft (dom), nicht im Browser
    #[DataProvider('listen')]
    public function test_ak1_angenaehert_datatables_1_bekommt_dom_mit_suche_vor_laenge(string $seite): void
    {
        $opt = $this->optionen($seite, '1.13.8');

        $this->assertIsString($opt['dom'] ?? null, 'dom ist gesetzt');
        $dom = (string) preg_replace('/<[^>]*>|"[^"]*"|\'[^\']*\'/', '', $opt['dom']);
        $this->assertNotFalse(strpos($dom, 'f'), 'Suchfeld (f) im dom');
        $this->assertNotFalse(strpos($dom, 'l'), 'Anzahl-Auswahl (l) im dom');
        $this->assertLessThan(strpos($dom, 'l'), strpos($dom, 'f'), 'Suchfeld steht vor der Anzahl-Auswahl');
    }

    // ------------------------------------------------------------------ AK2

    // AK2
    #[DataProvider('listen')]
    public function test_ak2_neu_button_steht_im_listenkopf_vor_der_tabelle_und_nicht_im_thead(string $seite): void
    {
        $xp = $this->verwaltungDom($this->verwaltungSeite($seite));
        $pfad = route(self::CREATE_ROUTEN[$seite], [], false);

        $kopf = $xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " userauth-listenkopf ")]');
        $this->assertCount(1, $kopf, 'genau ein .userauth-listenkopf');
        $this->assertCount(1, $xp->query('.//a[contains(@href, "'.$pfad.'")]', $kopf->item(0)), 'Neu-Button im Listenkopf');
        $this->assertCount(1, $xp->query('//table[contains(@class, "data-table")]/preceding::*[contains(@class, "userauth-listenkopf")]'), 'Listenkopf steht vor der Tabelle');
        $this->assertCount(0, $xp->query('//table//thead//a[contains(@href, "'.$pfad.'")]'), 'kein Neu-Button im thead');
    }

    // AK2
    #[DataProvider('listen')]
    public function test_ak2_thead_hat_eine_kopfzelle_je_spalte_und_die_aktionszelle_ohne_button(string $seite): void
    {
        $spalten = $this->optionen($seite, '2.1.8')['columns'];
        $xp = $this->verwaltungDom($this->verwaltungSeite($seite));

        $zellen = $xp->query('//table[contains(@class, "data-table")]/thead//th');
        $this->assertSame(count($spalten), $zellen->length, 'eine Kopfzelle je Spalte');
        $letzte = $zellen->item($zellen->length - 1);
        $this->assertInstanceOf(DOMElement::class, $letzte);
        $this->assertSame('action', $spalten[count($spalten) - 1]['data']);
        $this->assertCount(0, $xp->query('.//a|.//button|.//form', $letzte), 'Aktionszelle ohne Button');
    }

    // ------------------------------------------------------------------ AK3

    // AK3
    #[DataProvider('listen')]
    public function test_ak3_spalte_id_hat_klasse_userauth_zahl_und_nur_sie(string $seite): void
    {
        $spalten = $this->optionen($seite, '2.1.8')['columns'];

        $this->assertSame('id', $spalten[0]['data']);
        $this->assertSame('userauth-zahl', $spalten[0]['className'] ?? null);
        foreach (array_slice($spalten, 1) as $spalte) {
            $this->assertNotSame('userauth-zahl', $spalte['className'] ?? null, 'Spalte '.$spalte['data']);
        }
    }

    // ------------------------------------------------------------------ AK4

    // AK4
    #[DataProvider('cssDateien')]
    public function test_ak4_css_hat_regeln_fuer_hover_steuerleiste_paginierung_und_zahlenspalte(string $datei): void
    {
        $this->assertNotSame([], $this->regelnMit($datei, '/userauth-listenkopf/'), 'Steuerleiste .userauth-listenkopf');
        $this->assertNotSame([], $this->regelnMit($datei, '/userauth-zahl/'), '.userauth-zahl');
        $this->assertNotSame([], $this->regelnMit($datei, '/(dt-search|dataTables_filter)/'), 'Suchfeld');
        $this->assertNotSame([], $this->regelnMit($datei, '/(dt-length|dataTables_length)/'), 'Anzahl-Auswahl');
        $this->assertNotSame([], $this->regelnMit($datei, '/(dt-paging|dataTables_paginate)/'), 'Paginierung');
        $this->assertNotSame([], $this->regelnMit($datei, '/(tbody|dt-|data-table|userauth-tabelle)[^{]*:hover|:hover[^{]*(tbody|tr)/'), 'Zeilen-Hover');
    }

    // AK4
    #[DataProvider('cssDateien')]
    public function test_ak4_neue_regeln_enthalten_keine_hex_farbwerte(string $datei): void
    {
        $regeln = $this->regelnMit($datei, '/(dt-|dataTables|userauth-listenkopf|userauth-zahl)/');

        // Gegenprobe: die Auswahl greift (es gibt neue Regeln), die Hex-Erkennung schlägt bei einem Beispiel an
        $this->assertNotSame([], $regeln, 'Regeln mit dt-/dataTables-Selektoren vorhanden');
        $this->assertNotSame([], $this->regelnMit($datei, '/(userauth-listenkopf|userauth-zahl)/'), 'neue Regeln für Listenkopf und Zahlenspalte vorhanden');
        $this->assertSame(1, preg_match('/#[0-9a-fA-F]{3,8}\b/', 'color:#1a2b3c'));

        foreach ($regeln as $regel) {
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $regel['body']), 'Hex-Farbe in Regel '.$regel['selektoren']);
        }
    }

    // ------------------------------------------------------------------ AK5

    // AK5
    #[DataProvider('listen')]
    public function test_ak5_grundoptionen_bleiben_serverseitig_mit_ajax_und_sprachdatei(string $seite): void
    {
        $opt = $this->optionen($seite, '2.1.8');

        $this->assertTrue($opt['serverSide'] ?? null);
        $this->assertTrue($opt['processing'] ?? null);
        $this->assertTrue($opt['responsive'] ?? null);
        $this->assertStringEndsWith('vendor/laravelkit/DataTable_DE.json', $opt['language']['url'] ?? '');
        $this->assertSame(route($seite), $opt['ajax']['url'] ?? null);
        $this->assertSame('XMLHttpRequest', $opt['ajax']['headers']['X-Requested-With'] ?? null);
    }
}
