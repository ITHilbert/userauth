<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use ITHilbert\UserAuth\Database\Seeders\DatabaseSeeder;
use ITHilbert\UserAuth\Database\Seeders\UsersTableSeeder;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Spec userauth-seeder-admin (Dashboard #300).
 *
 * Form der Konsolenausgabe (Vertrag mit dem Coder):
 *  - Passwort (AK5): eine eigene Zeile `Passwort: <wert>` (genau einmal).
 *  - Kein Konto (AK3, Randfälle): Zeile mit `Kein Admin-Konto angelegt` und dem Grund `.lan`
 *    (z. B. `Kein Admin-Konto angelegt: keine .lan-Domain`).
 *  - Rolle admin fehlt: Zeile mit `Kein Admin-Konto angelegt` und `admin`.
 */
final class UsersTableSeederTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ID = 7;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://dashboard.lan']);
        config(['userauth.admin_passwort' => null]);
    }

    // ---------------------------------------------------------------- Hilfen

    private function rollenAnlegen(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer'],
            ['id' => self::ADMIN_ID, 'role' => 'admin', 'role_display' => 'Admin'],
        ]);
    }

    /** @return array<string, mixed> */
    private function benutzer(string $email, ?string $geloescht = null, int $id = 0): array
    {
        return array_filter([
            'id' => $id ?: null,
            'role_id' => 1,
            'name' => 'Vorhanden '.$email,
            'email' => $email,
            'password' => Hash::make('geheim-'.$email),
            'created_at' => '2024-01-02 03:04:05',
            'updated_at' => '2024-02-03 04:05:06',
            'deleted_at' => $geloescht,
        ], static fn ($v, $k) => $v !== null || $k === 'deleted_at', ARRAY_FILTER_USE_BOTH);
    }

    /** @return array{0: int, 1: string} Exit-Code und Konsolenausgabe */
    private function seederLaufen(string $klasse = UsersTableSeeder::class): array
    {
        $exit = Artisan::call('db:seed', ['--class' => $klasse, '--force' => true]);

        return [$exit, Artisan::output()];
    }

    private function passwortAusAusgabe(string $ausgabe): string
    {
        $this->assertSame(1, preg_match_all('/^\s*Passwort:\s*(\S+)\s*$/m', $ausgabe, $treffer), 'Genau eine Zeile "Passwort: <wert>" erwartet. Ausgabe: '.$ausgabe);

        return $treffer[1][0];
    }

    /** @return list<array<string, mixed>> */
    private function alleBenutzer(): array
    {
        return DB::table('users')->orderBy('id')->get()->map(fn ($z) => (array) $z)->all();
    }

    // ------------------------------------------------------------------ AK1

    // AK1
    public function test_vorhandene_benutzer_bleiben_mit_id_und_spalten_unveraendert_einzelner_lauf(): void
    {
        $this->rollenAnlegen();
        DB::table('users')->insert([
            $this->benutzer('a@example.de', null, 5),
            $this->benutzer('b@example.de', '2024-03-04 05:06:07', 9),
        ]);
        $vorher = $this->alleBenutzer();
        $this->assertCount(2, $vorher);

        [$exit] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame($vorher, $this->alleBenutzer());
    }

    // AK1
    public function test_vorhandene_benutzer_bleiben_unveraendert_ueber_den_database_seeder(): void
    {
        $this->rollenAnlegen();
        DB::table('users')->insert([
            $this->benutzer('a@example.de', null, 5),
            $this->benutzer('b@example.de', '2024-03-04 05:06:07', 9),
        ]);
        $vorher = $this->alleBenutzer();

        [$exit] = $this->seederLaufen(DatabaseSeeder::class);

        $this->assertSame(0, $exit);
        $this->assertSame($vorher, $this->alleBenutzer());
    }

    // AK1
    public function test_es_entsteht_kein_neuer_benutzer_wenn_benutzer_vorhanden_sind(): void
    {
        $this->rollenAnlegen();
        config(['userauth.admin_passwort' => 'Irgendein-Passwort-123']);
        DB::table('users')->insert([$this->benutzer('a@example.de', null, 5)]);

        [$exit] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertFalse(DB::table('users')->where('email', 'admin@admin.com')->exists());
    }

    // ------------------------------------------------------------------ AK2

    // AK2
    #[DataProvider('heimnetzUrls')]
    public function test_genau_ein_benutzer_entsteht_bei_lan_host(string $url): void
    {
        $this->rollenAnlegen();
        config(['app.url' => $url]);

        [$exit, $ausgabe] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame(1, DB::table('users')->count(), $ausgabe);
    }

    // AK2
    #[DataProvider('lokaleUrls')]
    public function test_genau_ein_benutzer_entsteht_bei_lokalem_host(string $url): void
    {
        $this->rollenAnlegen();
        config(['app.url' => $url]);

        [$exit, $ausgabe] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame(1, DB::table('users')->count(), $ausgabe);
    }

    // AK2
    #[DataProvider('lokaleUrls')]
    public function test_lokales_konto_hat_email_name_und_rolle_admin(string $url): void
    {
        $this->rollenAnlegen();
        config(['app.url' => $url]);

        $this->seederLaufen();

        $benutzer = DB::table('users')->first();
        $this->assertNotNull($benutzer);
        $this->assertSame('admin@admin.com', $benutzer->email);
        $this->assertSame('admin', $benutzer->name);
        $this->assertSame(self::ADMIN_ID, (int) $benutzer->role_id);
    }

    // AK2
    public function test_admin_konto_hat_email_und_name(): void
    {
        $this->rollenAnlegen();

        $this->seederLaufen();

        $benutzer = DB::table('users')->first();
        $this->assertNotNull($benutzer);
        $this->assertSame('admin@admin.com', $benutzer->email);
        $this->assertSame('admin', $benutzer->name);
    }

    // AK2
    public function test_admin_konto_hat_die_rolle_admin_ueber_roles_role_nicht_ueber_feste_id(): void
    {
        $this->rollenAnlegen();

        $this->seederLaufen();

        $benutzer = DB::table('users')->first();
        $this->assertNotNull($benutzer);
        $this->assertSame(self::ADMIN_ID, (int) $benutzer->role_id);
        $this->assertNotSame(1, (int) $benutzer->role_id);
    }

    // AK2 (Nicht-Ziel: email_verified_at bleibt null)
    public function test_email_verified_at_bleibt_null(): void
    {
        $this->rollenAnlegen();

        $this->seederLaufen();

        $benutzer = DB::table('users')->first();
        $this->assertNotNull($benutzer);
        $this->assertNull($benutzer->email_verified_at);
    }

    /** @return array<string, array{string}> */
    public static function heimnetzUrls(): array
    {
        return [
            'https' => ['https://dashboard.lan'],
            'mit Port und Pfad' => ['https://dashboard.lan:8443/app'],
            'http' => ['http://newlv.lan'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function lokaleUrls(): array
    {
        return [
            'localhost mit Port' => ['http://localhost:8004'],
            'localhost ohne Port' => ['http://localhost'],
            '127.0.0.1 mit Port' => ['http://127.0.0.1:8000'],
            'IPv6 Loopback mit Port' => ['http://[::1]:8000'],
            'IPv6 Loopback ohne Port' => ['http://[::1]'],
        ];
    }

    // ------------------------------------------------------------------ AK3

    // AK3, Randfall 3
    #[DataProvider('fremdeUrls')]
    public function test_kein_benutzer_ausserhalb_des_heimnetzes(string $url): void
    {
        $this->rollenAnlegen();
        config(['app.url' => $url]);

        [$exit, $ausgabe] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertStringContainsStringIgnoringCase('Kein Admin-Konto angelegt', $ausgabe);
    }

    // AK3
    #[DataProvider('fremdeUrls')]
    public function test_ausgabe_nennt_den_grund_keine_lan_domain_und_nicht_lokal(string $url): void
    {
        $this->rollenAnlegen();
        config(['app.url' => $url]);

        [, $ausgabe] = $this->seederLaufen();

        $this->assertStringContainsStringIgnoringCase('Kein Admin-Konto angelegt', $ausgabe);
        $this->assertStringContainsString('.lan', $ausgabe);
        $this->assertStringContainsStringIgnoringCase('lokal', $ausgabe);
    }

    /** @return array<string, array{string}> */
    public static function fremdeUrls(): array
    {
        return [
            'Internet' => ['https://www.example.de'],
            'IP im Heimnetz ohne .lan' => ['http://192.168.1.10'],
            'lan nur im Teilstring' => ['https://lan.example.de'],
            'localhost als Präfix' => ['http://localhost.example.de'],
            'localhost im Namen' => ['http://foo.localhost.de'],
            '127.0.0.2 nicht Loopback-Name' => ['http://127.0.0.2:8000'],
        ];
    }

    // Randfall 1
    public function test_host_foo_lan_example_de_endet_nicht_auf_lan_kein_konto(): void
    {
        $this->rollenAnlegen();
        config(['app.url' => 'https://foo.lan.example.de']);

        [$exit, $ausgabe] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertStringContainsStringIgnoringCase('Kein Admin-Konto angelegt', $ausgabe);
    }

    // Randfall 2
    public function test_grossschreibung_dashboard_lan_gilt_als_lan(): void
    {
        $this->rollenAnlegen();
        config(['app.url' => 'https://Dashboard.LAN']);

        [$exit, $ausgabe] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame(1, DB::table('users')->count(), $ausgabe);
        $this->assertSame('admin@admin.com', DB::table('users')->value('email'));
    }

    // Randfall 2
    #[DataProvider('grossgeschriebeneLokaleUrls')]
    public function test_grossschreibung_localhost_gilt_als_lokal(string $url): void
    {
        $this->rollenAnlegen();
        config(['app.url' => $url]);

        [$exit, $ausgabe] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame(1, DB::table('users')->count(), $ausgabe);
        $this->assertSame('admin@admin.com', DB::table('users')->value('email'));
    }

    /** @return array<string, array{string}> */
    public static function grossgeschriebeneLokaleUrls(): array
    {
        return [
            'LOCALHOST' => ['http://LOCALHOST'],
            'LocalHost mit Port' => ['http://LocalHost:8004'],
        ];
    }

    // Randfall 4
    #[DataProvider('urlsOhneHost')]
    public function test_app_url_leer_oder_ohne_host_kein_konto(?string $url): void
    {
        $this->rollenAnlegen();
        config(['app.url' => $url]);

        [$exit, $ausgabe] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertStringContainsStringIgnoringCase('Kein Admin-Konto angelegt', $ausgabe);
    }

    // Randfall 4
    #[DataProvider('urlsOhneHost')]
    public function test_app_url_leer_oder_ohne_host_nennt_grund_auf_der_konsole(?string $url): void
    {
        $this->rollenAnlegen();
        config(['app.url' => $url]);

        [, $ausgabe] = $this->seederLaufen();

        $this->assertStringContainsStringIgnoringCase('Kein Admin-Konto angelegt', $ausgabe);
        $this->assertStringContainsString('.lan', $ausgabe);
    }

    /** @return array<string, array{?string}> */
    public static function urlsOhneHost(): array
    {
        return [
            'nicht gesetzt' => [null],
            'leer' => [''],
            'nur Pfad' => ['/nur/ein/pfad'],
            'kein URL-Text' => ['kein host'],
        ];
    }

    // ------------------------------------------------------------------ AK4

    // AK4
    public function test_passwort_aus_der_konfiguration_passt_per_hash_check(): void
    {
        $this->rollenAnlegen();
        config(['userauth.admin_passwort' => 'Konfig-Passwort-42!']);

        $this->seederLaufen();

        $benutzer = DB::table('users')->first();
        $this->assertNotNull($benutzer);
        $this->assertSame('admin@admin.com', $benutzer->email);
        $this->assertTrue(Hash::check('Konfig-Passwort-42!', $benutzer->password));
    }

    // AK4
    public function test_konfiguriertes_passwort_erscheint_nicht_in_der_konsolenausgabe(): void
    {
        $this->rollenAnlegen();
        config(['userauth.admin_passwort' => 'Konfig-Passwort-42!']);

        [, $ausgabe] = $this->seederLaufen();

        // Gegenprobe: Seeder lief und hat das Konto angelegt.
        $this->assertSame(1, DB::table('users')->where('email', 'admin@admin.com')->count());
        $this->assertStringNotContainsString('Konfig-Passwort-42!', $ausgabe);
    }

    // ------------------------------------------------------------------ AK5

    // AK5
    public function test_ohne_konfiguriertes_passwort_ist_das_zufallspasswort_mindestens_20_zeichen_lang(): void
    {
        $this->rollenAnlegen();

        [, $ausgabe] = $this->seederLaufen();

        $passwort = $this->passwortAusAusgabe($ausgabe);
        $this->assertGreaterThanOrEqual(20, strlen($passwort));
    }

    // AK5
    public function test_zufallspasswort_wird_genau_einmal_ausgegeben(): void
    {
        $this->rollenAnlegen();

        [, $ausgabe] = $this->seederLaufen();

        $passwort = $this->passwortAusAusgabe($ausgabe);
        $this->assertSame(1, substr_count($ausgabe, $passwort));
    }

    // AK5
    public function test_hash_check_mit_dem_ausgegebenen_passwort_ist_wahr(): void
    {
        $this->rollenAnlegen();

        [, $ausgabe] = $this->seederLaufen();

        $passwort = $this->passwortAusAusgabe($ausgabe);
        $benutzer = DB::table('users')->where('email', 'admin@admin.com')->first();
        $this->assertNotNull($benutzer);
        $this->assertTrue(Hash::check($passwort, $benutzer->password));
    }

    // AK5, Randfall 8
    public function test_leer_gesetztes_passwort_zaehlt_als_nicht_konfiguriert(): void
    {
        $this->rollenAnlegen();
        config(['userauth.admin_passwort' => '']);

        [, $ausgabe] = $this->seederLaufen();

        $passwort = $this->passwortAusAusgabe($ausgabe);
        $this->assertGreaterThanOrEqual(20, strlen($passwort));
        $benutzer = DB::table('users')->where('email', 'admin@admin.com')->first();
        $this->assertNotNull($benutzer);
        $this->assertTrue(Hash::check($passwort, $benutzer->password));
        $this->assertFalse(Hash::check('', $benutzer->password));
    }

    // AK5
    public function test_zwei_laeufe_auf_leerer_tabelle_ergeben_verschiedene_passwoerter(): void
    {
        $this->rollenAnlegen();

        [, $erste] = $this->seederLaufen();
        $passwort1 = $this->passwortAusAusgabe($erste);
        $this->assertSame(1, DB::table('users')->count());

        DB::table('users')->delete();
        [, $zweite] = $this->seederLaufen();
        $passwort2 = $this->passwortAusAusgabe($zweite);

        $this->assertSame(1, DB::table('users')->count());
        $this->assertNotSame($passwort1, $passwort2);
    }

    // ------------------------------------------------------------------ AK6

    // AK6
    public function test_seeder_enthaelt_keinen_passwort_hash(): void
    {
        $quelle = (string) file_get_contents(__DIR__.'/../../src/Database/Seeders/UsersTableSeeder.php');

        $this->assertStringContainsString('UsersTableSeeder', $quelle);
        $this->assertStringNotContainsString('$2y$', $quelle);
        $this->assertDoesNotMatchRegularExpression('/\$argon2/i', $quelle);
    }

    // AK6
    public function test_seeder_enthaelt_kein_klartext_passwort(): void
    {
        $quelle = (string) file_get_contents(__DIR__.'/../../src/Database/Seeders/UsersTableSeeder.php');

        $this->assertStringContainsString('UsersTableSeeder', $quelle);
        $this->assertDoesNotMatchRegularExpression('/=>\s*[\'"]password[\'"]/', $quelle);
        $this->assertDoesNotMatchRegularExpression('/(Hash::make|bcrypt)\(\s*[\'"][^\'"]+[\'"]\s*\)/', $quelle);
    }

    // AK6
    public function test_konfiguration_nennt_admin_passwort_mit_env_ohne_vorgabewert(): void
    {
        $config = (string) file_get_contents(__DIR__.'/../../src/Config/config.php');

        $this->assertMatchesRegularExpression(
            '/[\'"]admin_passwort[\'"]\s*=>\s*env\(\s*[\'"]USERAUTH_ADMIN_PASSWORT[\'"]\s*\)/',
            $config,
        );
    }

    // ------------------------------------------------------------ Randfälle

    // Randfall 5
    public function test_nur_soft_geloeschte_benutzer_gelten_als_nicht_leer_kein_neues_konto(): void
    {
        $this->rollenAnlegen();
        DB::table('users')->insert([$this->benutzer('weg@example.de', '2024-03-04 05:06:07', 3)]);
        $vorher = $this->alleBenutzer();
        $this->assertCount(1, $vorher);

        [$exit] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame($vorher, $this->alleBenutzer());
        $this->assertFalse(DB::table('users')->where('email', 'admin@admin.com')->exists());
    }

    // Randfall 6
    public function test_fehlende_rolle_admin_legt_kein_konto_an_ohne_fehler(): void
    {
        DB::table('roles')->insert([['id' => 1, 'role' => 'dev', 'role_display' => 'Developer']]);

        [$exit, $ausgabe] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertStringContainsStringIgnoringCase('Kein Admin-Konto angelegt', $ausgabe);
    }

    // Randfall 6
    public function test_fehlende_rolle_admin_gibt_hinweis_auf_der_konsole(): void
    {
        DB::table('roles')->insert([['id' => 1, 'role' => 'dev', 'role_display' => 'Developer']]);

        [, $ausgabe] = $this->seederLaufen();

        $this->assertStringContainsStringIgnoringCase('Kein Admin-Konto angelegt', $ausgabe);
        $this->assertStringContainsString('Rolle admin', $ausgabe);
        $this->assertStringNotContainsString('dev', $ausgabe);
    }

    // Randfall 7
    public function test_zweiter_lauf_legt_kein_zweites_konto_an(): void
    {
        $this->rollenAnlegen();

        [, $erste] = $this->seederLaufen();
        $this->passwortAusAusgabe($erste);
        $this->assertSame(1, DB::table('users')->count());

        [$exit] = $this->seederLaufen();

        $this->assertSame(0, $exit);
        $this->assertSame(1, DB::table('users')->count());
    }

    // Randfall 7
    public function test_zweiter_lauf_laesst_das_passwort_unveraendert(): void
    {
        $this->rollenAnlegen();

        [, $erste] = $this->seederLaufen();
        $passwort = $this->passwortAusAusgabe($erste);
        $vorher = $this->alleBenutzer();
        $this->assertCount(1, $vorher);

        $this->seederLaufen();

        $this->assertSame($vorher, $this->alleBenutzer());
        $benutzer = DB::table('users')->first();
        $this->assertNotNull($benutzer);
        $this->assertTrue(Hash::check($passwort, $benutzer->password));
    }
}
