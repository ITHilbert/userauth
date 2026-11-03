<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Seitentitel der Bearbeiten-Seite einer Rechtegruppe (userauth-rechtenamen, Dashboard #498).
 * Abgedeckt: AK6.
 *
 * Gruppe 3 ist eine CRUD-Gruppe, Gruppe 4 eine Einzelrechte-Gruppe. Das Test-Layout gibt den Abschnitt `title`
 * als <title> aus (das Layout der Fixtures tut das nicht).
 */
final class RechtSeitentitelTest extends TestCase
{
    use RefreshDatabase;

    private string $layoutOrdner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->layoutOrdner = sys_get_temp_dir().'/userauth-titel-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->layoutOrdner.'/layouts');
        File::put($this->layoutOrdner.'/layouts/app.blade.php', "<!doctype html>\n<html><head><title>@yield('title')</title></head><body>\n@yield('content')\n@yield('js')\n</body></html>\n");

        View::addLocation(__DIR__.'/../Fixtures/views');
        View::getFinder()->prependLocation($this->layoutOrdner);
        config(['laravelkit.theme' => 'tailwind']);
        app()->setLocale('de');

        $jetzt = now();
        DB::table('roles')->insert(['id' => 1, 'role' => 'dev', 'role_display' => 'Dev', 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        foreach ([[3, 'alpha', 'Alpha', 0], [4, 'einzel', 'Einzel', 1]] as [$id, $name, $anzeige, $istGruppe]) {
            DB::table('permissions_groups')->insert([
                'id' => $id, 'group_name' => $name, 'group_display' => $anzeige, 'is_group' => $istGruppe,
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
        DB::table('permissions')->insert(['permission' => 'einzel_recht', 'permission_display' => 'Einzel Recht', 'group_id' => 4, 'crud' => 'single', 'created_at' => $jetzt, 'updated_at' => $jetzt]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->layoutOrdner);

        parent::tearDown();
    }

    /** @return array<string, array{0: int}> */
    public static function gruppen(): array
    {
        return ['CRUD-Gruppe' => [3], 'Einzelrechte-Gruppe' => [4]];
    }

    private function seite(int $gruppe): string
    {
        $antwort = $this->actingAs(User::factory()->create(['role_id' => 1]))->get('/permissions/edit/'.$gruppe);
        $antwort->assertOk();

        return $antwort->getContent();
    }

    /** @return list<string> Textinhalt aller Treffer des XPath-Ausdrucks */
    private function texte(string $html, string $xpath): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        $texte = [];
        foreach ((new DOMXPath($dom))->query($xpath) ?: [] as $knoten) {
            $texte[] = trim($knoten->textContent);
        }

        return $texte;
    }

    // AK6
    public function test_ak6_der_sprachschluessel_header_edit_heisst_recht_anpassen(): void
    {
        $this->assertSame('Recht anpassen', __('userauth::permission.header_edit'));
    }

    // AK6
    #[DataProvider('gruppen')]
    public function test_ak6_die_bearbeiten_seite_hat_den_titel_recht_anpassen(int $gruppe): void
    {
        $html = $this->seite($gruppe);

        // Gegenprobe: das Test-Layout gibt den Titel überhaupt aus
        $this->assertCount(1, $this->texte($html, '//title'));
        $this->assertSame(['Recht anpassen'], $this->texte($html, '//title'));
    }

    // AK6
    #[DataProvider('gruppen')]
    public function test_ak6_die_bearbeiten_seite_hat_die_ueberschrift_recht_anpassen(int $gruppe): void
    {
        $html = $this->seite($gruppe);

        $this->assertSame(['Recht anpassen'], $this->texte($html, '//h1'));
    }

    // AK6
    #[DataProvider('gruppen')]
    public function test_ak6_die_bearbeiten_seite_zeigt_den_alten_titel_rechts_anpassen_nicht(int $gruppe): void
    {
        $html = $this->seite($gruppe);

        // Gegenprobe: die Seite zeigt den neuen Titel
        $this->assertStringContainsString('Recht anpassen', $html);
        $this->assertStringNotContainsString('Rechts anpassen', $html);
    }
}
