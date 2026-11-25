<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * zugriff-403 (Dashboard #2183): `userauth.redirect_on_no_permission = '403'` (AK1–AK6, Randfall 1).
 *
 * Rollen: 2 admin, 3 user (Recht `role_read`), 4 zugang (Recht `zugang`, Rolle heißt `zugang`), 5 beides (beide Rechte).
 * Je Middleware gibt es eine Route, einen Benutzer mit Zugang und einen ohne.
 */
final class Zugriff403Test extends TestCase
{
    use RefreshDatabase;

    private const GEHEIM = 'GEHEIMER-INHALT';

    private const OHNE_ZUGANG = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $jetzt = now();
        foreach ([[1, 'dev'], [2, 'admin'], [3, 'user'], [4, 'zugang'], [5, 'beides']] as [$id, $name]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => ucfirst($name), 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }
        DB::table('permissions_groups')->insert(['id' => 1, 'group_name' => 'gruppe', 'group_display' => 'Gruppe', 'is_group' => 1, 'created_at' => $jetzt, 'updated_at' => $jetzt]);

        $rechte = [];
        foreach (['zugang', 'role_read', 'beta_read'] as $recht) {
            $rechte[$recht] = DB::table('permissions')->insertGetId([
                'permission' => $recht, 'permission_display' => $recht, 'group_id' => 1, 'crud' => 'single',
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
        foreach ([[3, 'role_read'], [4, 'zugang'], [5, 'zugang'], [5, 'role_read']] as [$rolle, $recht]) {
            DB::table('role_permission')->insert(['role_id' => $rolle, 'permission_id' => $rechte[$recht]]);
        }

        foreach (['perm' => 'hasPermission:zugang', 'and' => 'hasPermissionAnd:zugang,role_read', 'or' => 'hasPermissionOr:zugang,beta_read',
            'role' => 'hasRole:zugang', 'admin' => 'isAdmin'] as $pfad => $middleware) {
            Route::middleware(['web', $middleware])->match(['get', 'post'], '/_t/'.$pfad, fn () => self::GEHEIM);
        }
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
    }

    private function benutzer(int $rolle): User
    {
        return User::factory()->create(['role_id' => $rolle]);
    }

    /** @return array<string, array{0: string, 1: int}> Pfad und Rolle, die die Route erreicht */
    public static function middlewares(): array
    {
        return [
            'hasPermission' => ['/_t/perm', 4],
            'hasPermissionAnd' => ['/_t/and', 5],
            'hasPermissionOr' => ['/_t/or', 4],
            'hasRole' => ['/_t/role', 4],
            'isAdmin' => ['/_t/admin', 2],
        ];
    }

    // AK1
    #[DataProvider('middlewares')]
    public function test_ak1_ohne_recht_antwortet_403_ohne_umleitung_und_ohne_inhalt(string $pfad, int $erlaubteRolle): void
    {
        config(['userauth.redirect_on_no_permission' => '403']);

        // Gegenprobe: der Aufbau greift, mit Recht erscheint der Inhalt
        $this->actingAs($this->benutzer($erlaubteRolle))->get($pfad)->assertOk()->assertSee(self::GEHEIM);

        $antwort = $this->actingAs($this->benutzer(self::OHNE_ZUGANG))->get($pfad);

        $antwort->assertForbidden();
        $antwort->assertHeaderMissing('Location');
        $antwort->assertDontSee(self::GEHEIM);
    }

    // AK2
    #[DataProvider('middlewares')]
    public function test_ak2_json_client_bekommt_403_mit_json_body(string $pfad, int $erlaubteRolle): void
    {
        config(['userauth.redirect_on_no_permission' => '403']);

        // Gegenprobe: mit Recht antwortet die Route auch dem JSON-Client
        $this->actingAs($this->benutzer($erlaubteRolle))->getJson($pfad)->assertOk();

        $antwort = $this->actingAs($this->benutzer(self::OHNE_ZUGANG))->getJson($pfad);

        $antwort->assertForbidden();
        $antwort->assertJsonStructure(['message']);
    }

    // AK3
    #[DataProvider('middlewares')]
    public function test_ak3_gast_wird_weiter_zur_anmeldung_umgeleitet(string $pfad, int $erlaubteRolle): void
    {
        config(['userauth.redirect_on_no_permission' => '403']);

        // Gegenprobe: ein angemeldeter Benutzer ohne Recht bekommt in diesem Modus 403
        $this->actingAs($this->benutzer(self::OHNE_ZUGANG))->get($pfad)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->get($pfad)->assertRedirect(route('login'));
    }

    // AK4
    #[DataProvider('middlewares')]
    public function test_ak4_benutzer_mit_recht_erreicht_den_inhalt(string $pfad, int $erlaubteRolle): void
    {
        config(['userauth.redirect_on_no_permission' => '403']);

        $this->actingAs($this->benutzer($erlaubteRolle))->get($pfad)->assertOk()->assertSee(self::GEHEIM);
    }

    /** @return array<string, array{0: string, 1: int, 2: string}> */
    public static function middlewaresUndModi(): array
    {
        $faelle = [];
        foreach (self::middlewares() as $name => [$pfad, $rolle]) {
            $faelle[$name.' login'] = [$pfad, $rolle, 'login'];
            $faelle[$name.' no-permission'] = [$pfad, $rolle, 'no-permission'];
        }

        return $faelle;
    }

    // AK5
    #[DataProvider('middlewaresUndModi')]
    public function test_ak5_standard_und_no_permission_leiten_weiter_wie_bisher(string $pfad, int $erlaubteRolle, string $modus): void
    {
        config(['userauth.redirect_on_no_permission' => $modus]);

        // Gegenprobe: mit Recht erscheint der Inhalt
        $this->actingAs($this->benutzer($erlaubteRolle))->get($pfad)->assertOk()->assertSee(self::GEHEIM);

        $this->actingAs($this->benutzer(self::OHNE_ZUGANG))->get($pfad)->assertRedirect(route($modus));
    }

    // AK5
    public function test_ak5_ohne_gesetzte_einstellung_gilt_der_standard_login(): void
    {
        $this->assertSame('login', config('userauth.redirect_on_no_permission'));

        $this->actingAs($this->benutzer(self::OHNE_ZUGANG))->get('/_t/perm')->assertRedirect(route('login'));
    }

    // Randfall 1
    #[DataProvider('middlewares')]
    public function test_randfall1_post_ohne_recht_ergibt_403(string $pfad, int $erlaubteRolle): void
    {
        config(['userauth.redirect_on_no_permission' => '403']);

        // Gegenprobe: mit Recht erreicht der POST die Route
        $this->actingAs($this->benutzer($erlaubteRolle))->post($pfad)->assertOk()->assertSee(self::GEHEIM);

        $antwort = $this->actingAs($this->benutzer(self::OHNE_ZUGANG))->post($pfad);

        $antwort->assertForbidden();
        $antwort->assertDontSee(self::GEHEIM);
    }

    /**
     * Zeilen eines Textes, die alle Teile enthalten.
     *
     * @param  list<string>  $teile
     * @return list<int> Zeilennummern
     */
    private function zeilenMit(string $text, array $teile): array
    {
        $treffer = [];
        foreach (explode("\n", $text) as $nr => $zeile) {
            foreach ($teile as $teil) {
                if (! str_contains($zeile, $teil)) {
                    continue 2;
                }
            }
            $treffer[] = $nr;
        }

        return $treffer;
    }

    // AK6
    #[Group('arch')]
    public function test_ak6_config_kommentar_nennt_den_wert_403(): void
    {
        // Gegenprobe der Prüfhilfe: der bisherige Kommentar ohne 403 gilt nicht als Treffer
        $this->assertSame([], $this->zeilenMit("// Route wenn Rechte fehlen: 'login' oder 'no-permission'", ["'403'"]));

        $zeilen = explode("\n", (string) file_get_contents(__DIR__.'/../../src/Config/config.php'));
        $nr = array_key_first(array_filter($zeilen, fn (string $z): bool => str_contains($z, "'redirect_on_no_permission' =>")));
        $this->assertNotNull($nr);

        $kommentar = implode("\n", array_slice($zeilen, max(0, $nr - 3), 3));
        $this->assertStringContainsString("'403'", $kommentar);
    }

    // AK6
    #[Group('arch')]
    public function test_ak6_readme_erklaert_403_im_abschnitt_zu_den_middlewares_bzw_rechten(): void
    {
        $readme = (string) file_get_contents(__DIR__.'/../../README.md');
        $zeilen = explode("\n", $readme);

        $treffer = $this->zeilenMit($readme, ['redirect_on_no_permission', "'403'"]);
        $this->assertNotSame([], $treffer, 'README nennt redirect_on_no_permission mit dem Wert \'403\'');

        $ueberschrift = '';
        for ($i = $treffer[0]; $i >= 0; $i--) {
            if (str_starts_with($zeilen[$i], '#')) {
                $ueberschrift = $zeilen[$i];
                break;
            }
        }
        $this->assertMatchesRegularExpression('/middleware|recht|berechtig|zugriff/i', $ueberschrift);
    }
}
