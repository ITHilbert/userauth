<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Application\Zugriff\Zugriffspruefung;
use ITHilbert\UserAuth\Database\Seeders\RolesTableSeeder;
use ITHilbert\UserAuth\Database\Seeders\UsersTableSeeder;
use ITHilbert\UserAuth\Domain\Zugriff\Rolle;
use ITHilbert\UserAuth\Entities\Role;
use ITHilbert\UserAuth\Http\Middleware\isDev;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * userauth-nur-admin (Dashboard #357): privilegiert ist nur die Rolle mit dem Schlüssel `admin`, `dev` ist eine
 * gewöhnliche Rolle (AK1–AK6, Randfälle 1–3).
 *
 * Das Schema baut jeder Test selbst auf (Paket-Migrationen); die Migrationstests nehmen die eine neue Paket-Migration
 * (der Name ist bewusst nicht festgelegt) aus der Migrationshistorie und führen sie danach erneut aus.
 * Die Tests `*_ohne_tabellen_*` starten ohne Schema.
 *
 * Verneinungen haben eine Gegenprobe: erst zeigt eine Rolle `admin` im selben Test, dass der Aufbau greift.
 */
final class NurAdminTest extends TestCase
{
    /** Alle Paket-Migrationen vor diesem Feature. */
    private const BEKANNTE_MIGRATIONEN = [
        '2019_10_12_000000_create_my_users_table',
        '2020_04_23_133628_create_roles_table',
        '2020_04_23_133642_create_permissions_table',
        '2020_04_23_133643_create_permissions_groups_table',
        '2020_04_23_140937_create_role_permission_table',
        '2026_02_17_000000_create_user_auth_audit_logs_table',
        '2026_02_17_100000_add_2fa_columns_to_users_table',
        '2026_03_28_000000_create_password_policy_tables',
        '2026_03_28_000001_create_teams_tables',
        '2026_09_29_000000_create_user_social_accounts_table',
        '2026_09_29_000001_create_user_pending_social_links_table',
        '2026_10_23_000000_make_role_id_nullable_on_users_table',
        '2026_10_23_100000_create_password_reset_tokens_table',
        '2026_10_23_200000_clear_remember_tokens_on_users_table',
    ];

    /** Prüfungen je Route/Gate/Blade; ein Nutzer besteht sie oder nicht. */
    private const PRUEFUNGEN = ['permission', 'and', 'or', 'role', 'roledev', 'roleadmin', 'admin', 'dev', 'gate', 'can'];

    protected function defineDatabaseMigrations(): void
    {
        // bewusst leer: das Schema baut jeder Test selbst auf
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('userauth.two_factor_enabled', false);
        $app['config']->set('userauth.audit_log_enabled', false);
        $app['config']->set('userauth.password_policy.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');

        Route::middleware(['web', 'hasPermission:b'])->get('/_t/permission', fn () => 'OK');
        Route::middleware(['web', 'hasPermissionAnd:a,b'])->get('/_t/and', fn () => 'OK');
        Route::middleware(['web', 'hasPermissionOr:a,b'])->get('/_t/or', fn () => 'OK');
        Route::middleware(['web', 'hasRole:finanzen'])->get('/_t/role', fn () => 'OK');
        Route::middleware(['web', 'hasRole:dev'])->get('/_t/roledev', fn () => 'OK');
        Route::middleware(['web', 'hasRole:admin'])->get('/_t/roleadmin', fn () => 'OK');
        Route::middleware(['web', 'isAdmin'])->get('/_t/admin', fn () => 'OK');
        Route::middleware(['web', 'isDev'])->get('/_t/dev', fn () => 'OK');

        if (! str_contains($this->name(), '_ohne_tabellen_')) {
            $this->paketMigrationenAusfuehren();
        }
    }

    // ---------------------------------------------------------------- Hilfen: Schema und Daten

    private function paketMigrationenAusfuehren(): void
    {
        $exit = Artisan::call('migrate', [
            '--path' => [dirname(__DIR__, 2).'/src/Database/Migrations'],
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
    }

    private function paketMigrationenZurueckrollen(): void
    {
        $exit = Artisan::call('migrate:rollback', [
            '--path' => [dirname(__DIR__, 2).'/src/Database/Migrations'],
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
    }

    private function rolle(int $id, string $schluessel, bool $geloescht = false): void
    {
        $jetzt = now();
        DB::table('roles')->insert([
            'id' => $id, 'role' => $schluessel, 'role_display' => $schluessel === '' ? 'leer' : trim($schluessel),
            'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => $geloescht ? $jetzt : null,
        ]);
    }

    /** Rechte `a`, `b` und `impersonate_users` (Ids 1–3). */
    private function rechteAnlegen(): void
    {
        $jetzt = now();
        foreach (['a', 'b', 'impersonate_users'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
    }

    private function rolleHatRecht(int $roleId, string $recht): void
    {
        DB::table('role_permission')->insert([
            'role_id' => $roleId,
            'permission_id' => DB::table('permissions')->where('permission', $recht)->value('id'),
        ]);
    }

    /** Nutzer frisch aus der Datenbank (die Zugriffsprüfung merkt sich die Rolle je Nutzer-Objekt). */
    private function nutzerMitRolle(int|string|null $roleId, ?int $teamRoleId = null): User
    {
        if ($roleId === null) {
            // Die Spalte ist nullable, ein Nutzer ohne Rolle existiert auch nur im Speicher (Fremd-Model)
            return User::factory()->make(['role_id' => null]);
        }

        $user = User::factory()->create(['role_id' => $roleId]);

        if ($teamRoleId !== null) {
            $teamId = (int) DB::table('teams')->insertGetId(['name' => 'Team '.$user->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('team_user')->insert(['team_id' => $teamId, 'user_id' => $user->id, 'role_id' => $teamRoleId, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('id', $user->id)->update(['current_team_id' => $teamId]);
        }

        return User::query()->findOrFail($user->id);
    }

    /** @return array<string, bool> */
    private function alle(bool $wert): array
    {
        return array_fill_keys(self::PRUEFUNGEN, $wert);
    }

    /**
     * Ergebnis aller Prüfungen für den Nutzer: Routen (Middlewares), Gate und Blade `@can`.
     *
     * @return array<string, bool>
     */
    private function pruefungen(User $user): array
    {
        $ergebnis = [];

        foreach (['permission', 'and', 'or', 'role', 'roledev', 'roleadmin', 'admin', 'dev'] as $name) {
            $response = $this->actingAs($user)->get('/_t/'.$name);
            $this->assertLessThan(500, $response->getStatusCode(), "/_t/{$name} darf keinen Serverfehler liefern");
            $ergebnis[$name] = $response->getStatusCode() === 200 && $response->getContent() === 'OK';
        }

        $this->actingAs($user);
        $ergebnis['gate'] = Gate::allows('beliebig');
        $ergebnis['can'] = trim(Blade::render("@can('beliebig') JA @else NEIN @endcan")) === 'JA';

        return $ergebnis;
    }

    /**
     * Legt die Rolle `admin` (id 90) an und zeigt, dass der Aufbau greift: Ein Admin besteht alle Prüfungen.
     */
    private function gegenprobeAdminBestehtAlles(): void
    {
        $this->rolle(90, 'admin');
        $this->assertSame($this->alle(true), $this->pruefungen($this->nutzerMitRolle(90)), 'Gegenprobe: Admin besteht alle Prüfungen');
    }

    // ---------------------------------------------------------------- AK1

    /** @return array<string, array{0: int}> */
    public static function adminIds(): array
    {
        return ['ID 1' => [1], 'ID 2' => [2], 'ID 4' => [4], 'ID 99' => [99]];
    }

    // AK1
    #[DataProvider('adminIds')]
    public function test_rolle_mit_schluessel_admin_besteht_jede_pruefung_unabhaengig_von_der_id(int $id): void
    {
        $this->rechteAnlegen();
        $this->rolle(50, 'user');
        $this->assertSame($this->alle(false), $this->pruefungen($this->nutzerMitRolle(50)), 'Gegenprobe: normale Rolle besteht nichts');

        $this->rolle($id, 'admin');

        $this->assertSame($this->alle(true), $this->pruefungen($this->nutzerMitRolle($id)));
    }

    // AK1
    #[DataProvider('adminIds')]
    public function test_admin_erfuellt_hasrole_dev_und_jede_andere_rolle_auch_ohne_eigene_rechte(int $id): void
    {
        $this->rolle($id, 'admin');
        $admin = $this->nutzerMitRolle($id);

        $this->actingAs($admin)->get('/_t/roledev')->assertOk()->assertSee('OK');
        $this->actingAs($admin)->get('/_t/role')->assertOk()->assertSee('OK');
        $this->actingAs($admin)->get('/_t/roleadmin')->assertOk()->assertSee('OK');
    }

    /** @return array<string, array{0: int}> */
    public static function devIds(): array
    {
        return ['ID 1' => [1], 'ID 2' => [2], 'ID 5' => [5]];
    }

    // AK1
    #[DataProvider('devIds')]
    public function test_rolle_mit_schluessel_dev_ist_nicht_privilegiert_hat_aber_ihren_eigenen_rollennamen(int $id): void
    {
        $this->rechteAnlegen();
        $this->gegenprobeAdminBestehtAlles();

        $this->rolle($id, 'dev');

        $erwartet = $this->alle(false);
        $erwartet['roledev'] = true;
        $this->assertSame($erwartet, $this->pruefungen($this->nutzerMitRolle($id)));
    }

    // AK1
    public function test_rolle_dev_hat_nur_ihre_eigenen_rechte(): void
    {
        $this->rechteAnlegen();
        $this->gegenprobeAdminBestehtAlles();
        $this->rolle(1, 'dev');
        $this->rolleHatRecht(1, 'b');

        $erwartet = $this->alle(false);
        $erwartet['permission'] = true;
        $erwartet['or'] = true;
        $erwartet['roledev'] = true;
        $this->assertSame($erwartet, $this->pruefungen($this->nutzerMitRolle(1)));
    }

    // AK1
    public function test_rolle_dev_erfuellt_weder_admin_noch_eine_fremde_rolle(): void
    {
        $this->gegenprobeAdminBestehtAlles();
        $this->rolle(1, 'dev');
        $dev = $this->nutzerMitRolle(1);

        $this->actingAs($dev)->get('/_t/roledev')->assertOk();
        $this->assertNotSame(200, $this->actingAs($dev)->get('/_t/roleadmin')->getStatusCode());
        $this->assertNotSame(200, $this->actingAs($dev)->get('/_t/role')->getStatusCode());
        $this->assertNotSame(200, $this->actingAs($dev)->get('/_t/admin')->getStatusCode());
    }

    /** @return array<string, array{0: int, 1: string}> */
    public static function rollenMitIdEinsOderZweiOhneAdminSchluessel(): array
    {
        return [
            'ID 1 chef' => [1, 'chef'],
            'ID 2 chef' => [2, 'chef'],
            'ID 1 user' => [1, 'user'],
            'ID 2 editor' => [2, 'editor'],
        ];
    }

    // AK1
    #[DataProvider('rollenMitIdEinsOderZweiOhneAdminSchluessel')]
    public function test_eine_rolle_mit_id_1_oder_2_aber_anderem_schluessel_ist_nicht_privilegiert(int $id, string $schluessel): void
    {
        $this->rechteAnlegen();
        $this->gegenprobeAdminBestehtAlles();

        $this->rolle($id, $schluessel);

        $this->assertSame($this->alle(false), $this->pruefungen($this->nutzerMitRolle($id)));
    }

    // AK1
    public function test_eine_gewoehnliche_rolle_erfuellt_nur_ihren_eigenen_rollennamen(): void
    {
        $this->gegenprobeAdminBestehtAlles();
        $this->rolle(2, 'finanzen');

        $erwartet = $this->alle(false);
        $erwartet['role'] = true;
        $this->assertSame($erwartet, $this->pruefungen($this->nutzerMitRolle(2)));
    }

    /** @return array<string, array{0: string}> */
    public static function ungueltigeRollen(): array
    {
        return ['gelöschte Rolle admin' => ['geloescht'], 'nicht vorhandene Rolle' => ['fehlt'], 'role_id 0' => ['null'], 'role_id NULL' => ['ohne']];
    }

    // AK1
    #[DataProvider('ungueltigeRollen')]
    public function test_gelöschte_fehlende_oder_ungültige_rollen_bleiben_unprivilegiert(string $fall): void
    {
        $this->rechteAnlegen();
        $this->gegenprobeAdminBestehtAlles();
        // gelöschte Rolle mit Schlüssel admin neben der aktiven (der Schlüssel ist per Index eindeutig; ein Host kann ihn gelockert haben)
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['role']);
        });
        $this->rolle(91, 'admin', geloescht: true);

        $roleId = match ($fall) {
            'geloescht' => 91,
            'fehlt' => 987654,
            'null' => 0,
            'ohne' => null,
        };

        $this->assertSame($this->alle(false), $this->pruefungen($this->nutzerMitRolle($roleId)));
    }

    // AK1
    public function test_die_rollen_entitaet_gewaehrt_jedes_recht_nur_dem_schluessel_admin(): void
    {
        $dev = Role::create(['role' => 'dev', 'role_display' => 'Developer']);
        $admin = Role::create(['role' => 'admin', 'role_display' => 'Admin']);

        $this->assertTrue($admin->hasPermission('irgendein_recht'), 'Gegenprobe: admin hat jedes Recht');
        $this->assertFalse($dev->hasPermission('irgendein_recht'));
        $this->assertFalse($dev->hasPermissionOr('a, b'));
        $this->assertFalse($dev->hasPermissionAnd('a, b'));
    }

    // ---------------------------------------------------------------- Randfälle 1–3

    // Randfall 1
    public function test_mehrere_rollen_mit_schluessel_admin_privilegiert_ist_jede_nicht_geloeschte(): void
    {
        $this->rechteAnlegen();
        // Der Schlüssel ist per Index eindeutig; ein Host kann ihn gelockert haben.
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['role']);
        });
        $this->rolle(4, 'admin', geloescht: true);
        $this->rolle(6, 'admin');
        $this->rolle(7, 'admin');

        $this->assertSame($this->alle(true), $this->pruefungen($this->nutzerMitRolle(6)), 'Gegenprobe: nicht gelöschte Rolle admin');
        $this->assertSame($this->alle(true), $this->pruefungen($this->nutzerMitRolle(7)));
        $this->assertSame($this->alle(false), $this->pruefungen($this->nutzerMitRolle(4)));
    }

    // Randfall 2
    public function test_mit_teams_gilt_die_rolle_des_aktiven_teams_admin_im_team_ist_privilegiert(): void
    {
        $this->rechteAnlegen();
        $this->rolle(1, 'dev');
        $this->rolle(2, 'admin');
        $this->rolle(3, 'user');
        $teamAdmin = $this->nutzerMitRolle(3, 2);

        config(['userauth.teams.enabled' => false]);
        $this->assertSame($this->alle(false), $this->pruefungen($this->nutzerMitRolle($teamAdmin->role_id)), 'Gegenprobe: ohne Teams zählt die globale Rolle');
        config(['userauth.teams.enabled' => true]);
        $this->assertSame($this->alle(true), $this->pruefungen(User::query()->findOrFail($teamAdmin->id)));
    }

    // Randfall 2
    public function test_mit_teams_gilt_die_rolle_des_aktiven_teams_globaler_admin_mit_teamrolle_user_ist_nicht_privilegiert(): void
    {
        $this->rechteAnlegen();
        $this->rolle(2, 'admin');
        $this->rolle(3, 'user');
        $globalAdmin = $this->nutzerMitRolle(2, 3);

        config(['userauth.teams.enabled' => false]);
        $this->assertSame($this->alle(true), $this->pruefungen(User::query()->findOrFail($globalAdmin->id)), 'Gegenprobe: ohne Teams zählt die globale Rolle admin');
        config(['userauth.teams.enabled' => true]);
        $this->assertSame($this->alle(false), $this->pruefungen(User::query()->findOrFail($globalAdmin->id)));
    }

    // Randfall 2
    public function test_mit_teams_ist_die_teamrolle_dev_nicht_privilegiert_auch_bei_globalem_admin(): void
    {
        $this->rechteAnlegen();
        $this->rolle(1, 'dev');
        $this->rolle(2, 'admin');
        $globalAdmin = $this->nutzerMitRolle(2, 1);

        config(['userauth.teams.enabled' => false]);
        $this->assertSame($this->alle(true), $this->pruefungen(User::query()->findOrFail($globalAdmin->id)), 'Gegenprobe: ohne Teams zählt die globale Rolle admin');
        config(['userauth.teams.enabled' => true]);

        $erwartet = $this->alle(false);
        $erwartet['roledev'] = true;
        $this->assertSame($erwartet, $this->pruefungen(User::query()->findOrFail($globalAdmin->id)));
    }

    /** @return array<string, array{0: string}> */
    public static function andereSchreibungen(): array
    {
        return [
            'Admin' => ['Admin'],
            'ADMIN' => ['ADMIN'],
            'Leerzeichen davor und danach' => [' admin '],
            'Leerzeichen davor' => [' admin'],
            'Zeilenumbruch danach' => ["admin\n"],
            'leer' => [''],
        ];
    }

    // Randfall 3
    #[DataProvider('andereSchreibungen')]
    public function test_schluessel_mit_anderer_schreibung_als_admin_ist_nicht_privilegiert(string $schluessel): void
    {
        $this->rechteAnlegen();
        $this->gegenprobeAdminBestehtAlles();

        $this->rolle(3, $schluessel);

        $this->assertSame($this->alle(false), $this->pruefungen($this->nutzerMitRolle(3)));
    }

    // ---------------------------------------------------------------- AK2

    /** @return array<string, array{0: string}> */
    public static function rechteverwaltungRoutenGet(): array
    {
        return ['permission' => ['permission'], 'permission.create' => ['permission.create']];
    }

    // AK2
    public function test_die_paket_vorgabe_der_rechteverwaltung_verlangt_nicht_mehr_die_rolle_dev(): void
    {
        $middleware = config('userauth.routes.permissions_middleware');

        $this->assertIsArray($middleware);
        $this->assertContains('auth', $middleware, 'Gegenprobe: die Vorgabe ist gelesen');
        $this->assertNotContains('hasRole:dev', $middleware);
        $this->assertContains('hasRole:admin', $middleware);
    }

    // AK2
    #[DataProvider('rechteverwaltungRoutenGet')]
    public function test_die_rechteverwaltung_ist_fuer_die_rolle_admin_erreichbar(string $route): void
    {
        $this->rolle(7, 'admin');

        $this->actingAs($this->nutzerMitRolle(7))->get(route($route))->assertOk();
    }

    // AK2
    #[DataProvider('rechteverwaltungRoutenGet')]
    public function test_die_rechteverwaltung_ist_fuer_die_rolle_dev_nicht_erreichbar(string $route): void
    {
        $this->rolle(7, 'admin');
        $this->rolle(1, 'dev');
        $this->actingAs($this->nutzerMitRolle(7))->get(route($route))->assertOk();

        $antwort = $this->actingAs($this->nutzerMitRolle(1))->get(route($route));

        $this->assertNotSame(200, $antwort->getStatusCode());
        $this->assertLessThan(500, $antwort->getStatusCode());
    }

    // AK2
    public function test_dev_kann_ueber_die_rechteverwaltung_keine_gruppe_anlegen_admin_schon(): void
    {
        $this->rolle(1, 'dev');
        $this->rolle(2, 'admin');
        $daten = ['group_display' => 'Neue Gruppe', 'group_name' => 'neue_gruppe', 'ckPermissionGroup' => 'on'];

        $antwort = $this->actingAs($this->nutzerMitRolle(1))->post(route('permission.store'), $daten);
        $this->assertLessThan(500, $antwort->getStatusCode());
        $this->assertSame(0, DB::table('permissions_groups')->where('group_name', 'neue_gruppe')->count());

        $this->actingAs($this->nutzerMitRolle(2))->post(route('permission.store'), $daten);
        $this->assertSame(1, DB::table('permissions_groups')->where('group_name', 'neue_gruppe')->count(), 'Gegenprobe: Admin darf anlegen');
    }

    // ---------------------------------------------------------------- AK3

    // AK3
    public function test_der_alias_isdev_bleibt_registriert(): void
    {
        $this->assertSame(isDev::class, $this->app['router']->getMiddleware()['isDev'] ?? null);
        $this->assertArrayHasKey('isAdmin', $this->app['router']->getMiddleware(), 'Gegenprobe: isAdmin ist registriert');
    }

    // AK3
    #[DataProvider('adminIds')]
    public function test_isdev_laesst_den_admin_durch_wie_isadmin(int $id): void
    {
        $this->rolle($id, 'admin');
        $admin = $this->nutzerMitRolle($id);

        $this->actingAs($admin)->get('/_t/admin')->assertOk()->assertSee('OK');
        $this->actingAs($admin)->get('/_t/dev')->assertOk()->assertSee('OK');
    }

    // AK3
    public function test_isdev_verweigert_die_rolle_dev_und_normale_rollen_wie_isadmin(): void
    {
        $this->rolle(1, 'dev');
        $this->rolle(2, 'admin');
        $this->rolle(3, 'user');
        $this->actingAs($this->nutzerMitRolle(2))->get('/_t/dev')->assertOk();

        foreach ([1, 3] as $roleId) {
            $user = $this->nutzerMitRolle($roleId);
            $isAdmin = $this->actingAs($user)->get('/_t/admin');
            $isDev = $this->actingAs($user)->get('/_t/dev');

            $this->assertNotSame(200, $isDev->getStatusCode(), "Rolle {$roleId} darf isDev nicht bestehen");
            $this->assertSame($isAdmin->getStatusCode(), $isDev->getStatusCode());
            $this->assertSame($isAdmin->headers->get('Location'), $isDev->headers->get('Location'));
            $this->assertStringNotContainsString('OK', (string) $isDev->getContent());
        }
    }

    // AK3
    public function test_isdev_behandelt_gaeste_wie_isadmin(): void
    {
        $this->rolle(2, 'admin');
        $this->actingAs($this->nutzerMitRolle(2))->get('/_t/dev')->assertOk();
        auth()->logout();
        $this->flushSession();

        $isAdmin = $this->get('/_t/admin');
        $isDev = $this->get('/_t/dev');

        $this->assertNotSame(200, $isDev->getStatusCode());
        $this->assertSame($isAdmin->getStatusCode(), $isDev->getStatusCode());
        $this->assertSame($isAdmin->headers->get('Location'), $isDev->headers->get('Location'));
    }

    // AK3
    public function test_zugriffspruefung_und_rolle_kennen_keine_eigene_dev_pruefung(): void
    {
        $this->assertTrue(method_exists(Rolle::class, 'istPrivilegiert'), 'Gegenprobe: die Privilegprüfung existiert');
        $this->assertTrue(method_exists(Zugriffspruefung::class, 'istPrivilegiert'), 'Gegenprobe: die Privilegprüfung existiert');

        foreach ([Rolle::class, Zugriffspruefung::class] as $klasse) {
            $dev = array_filter(get_class_methods($klasse), fn (string $methode): bool => preg_match('/dev/i', $methode) === 1);
            $this->assertSame([], array_values($dev), "{$klasse} darf keine Dev-Prüfung haben");
        }
    }

    // ---------------------------------------------------------------- AK4

    /** @return list<array{string, string}> */
    private function rollenInDerTabelle(): array
    {
        return DB::table('roles')->orderBy('role')->get(['role', 'role_display'])->map(fn ($z): array => [$z->role, $z->role_display])->all();
    }

    // AK4
    public function test_der_rollen_seeder_legt_auf_leerer_tabelle_nur_admin_und_user_an(): void
    {
        $this->assertSame(0, DB::table('roles')->count());

        $this->seed(RolesTableSeeder::class);

        $this->assertSame([['admin', 'Admin'], ['user', 'Anwender']], $this->rollenInDerTabelle());
    }

    // AK4
    public function test_der_rollen_seeder_legt_keine_rolle_dev_an_auch_nicht_ein_zweites_mal(): void
    {
        $this->seed(RolesTableSeeder::class);
        $this->assertSame(2, DB::table('roles')->count(), 'Gegenprobe: der Seeder hat gelaufen');

        $this->seed(RolesTableSeeder::class);

        $this->assertSame(0, DB::table('roles')->where('role', 'dev')->count());
        $this->assertSame(2, DB::table('roles')->count());
    }

    // AK4
    public function test_der_rollen_seeder_laesst_vorhandene_zeilen_unberuehrt(): void
    {
        $this->rolle(1, 'dev');
        DB::table('roles')->insert(['id' => 5, 'role' => 'admin', 'role_display' => 'Chef', 'created_at' => '2024-01-02 03:04:05', 'updated_at' => '2024-02-03 04:05:06', 'deleted_at' => null]);
        $vorher = DB::table('roles')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all();

        $this->seed(RolesTableSeeder::class);

        $this->assertNotNull(DB::table('roles')->where('role', 'user')->first(), 'Gegenprobe: fehlende Rolle user wurde ergänzt');
        $nachher = DB::table('roles')->whereIn('id', [1, 5])->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all();
        $this->assertSame($vorher, $nachher);
        $this->assertSame(1, DB::table('roles')->where('role', 'admin')->count());
        $this->assertSame(1, DB::table('roles')->where('role', 'dev')->count());
    }

    private function seederUmgebung(): void
    {
        config(['app.url' => 'https://dashboard.lan', 'userauth.admin_passwort' => 'ein-geheimes-passwort']);
    }

    // AK4
    public function test_der_benutzer_seeder_gibt_admin_admin_com_die_rolle_mit_schluessel_admin(): void
    {
        $this->seederUmgebung();
        $this->seed(RolesTableSeeder::class);
        $adminId = (int) DB::table('roles')->where('role', 'admin')->value('id');
        $this->assertGreaterThan(0, $adminId, 'Gegenprobe: Rolle admin existiert');

        $this->seed(UsersTableSeeder::class);

        $konto = DB::table('users')->where('email', 'admin@admin.com')->first();
        $this->assertNotNull($konto);
        $this->assertSame($adminId, (int) $konto->role_id);
    }

    /** @return array<string, array{0: int}> */
    public static function adminIdsDesSeeders(): array
    {
        return ['admin auf ID 2' => [2], 'admin auf ID 7' => [7], 'admin auf ID 1' => [1]];
    }

    // AK4
    #[DataProvider('adminIdsDesSeeders')]
    public function test_das_geseedete_konto_ist_privilegiert_auch_wenn_admin_nicht_id_2_hat(int $adminId): void
    {
        $this->seederUmgebung();
        $this->rechteAnlegen();
        $this->rolle($adminId, 'admin');
        $this->rolle($adminId === 1 ? 2 : 1, 'dev');

        $this->seed(UsersTableSeeder::class);

        $konto = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        $this->assertSame($adminId, (int) $konto->role_id);
        $this->assertSame($this->alle(true), $this->pruefungen($konto));
    }

    // AK4
    public function test_der_benutzer_seeder_nimmt_admin_und_nicht_dev_wenn_beide_rollen_existieren(): void
    {
        $this->seederUmgebung();
        $this->rolle(1, 'dev');
        $this->rolle(2, 'admin');

        $this->seed(UsersTableSeeder::class);

        $this->assertSame(2, (int) DB::table('users')->where('email', 'admin@admin.com')->value('role_id'));
    }

    // AK4
    public function test_der_benutzer_seeder_legt_ohne_rolle_admin_kein_konto_an_auch_wenn_dev_existiert(): void
    {
        $this->seederUmgebung();
        $this->rolle(1, 'dev');

        $this->seed(UsersTableSeeder::class);

        $this->assertSame(0, DB::table('users')->count());

        // Gegenprobe: mit Rolle admin entsteht das Konto
        $this->rolle(2, 'admin');
        $this->seed(UsersTableSeeder::class);
        $this->assertSame(1, DB::table('users')->count());
    }

    // ---------------------------------------------------------------- AK5: Migration dev → admin

    /**
     * Stand vor dem Update: alle bisherigen Paket-Migrationen gelaufen, die neue steht noch aus.
     * Rollen: 1 dev, 2 admin, 3 user. Nutzer: 21 dev, 22 dev (weich gelöscht), 23 admin, 24 user, 25 dev, 26 ohne Rolle.
     * Teams: Team 1 mit 21 (dev), 24 (user); Team 2 mit 23 (dev), 25 (dev).
     */
    private function standVorDemUpdate(): void
    {
        $neu = DB::table('migrations')->whereNotIn('migration', self::BEKANNTE_MIGRATIONEN)->pluck('migration');
        $this->assertCount(1, $neu, 'Es muss genau eine neue Paket-Migration für AK5 geben, gefunden: '.$neu->implode(', '));
        DB::table('migrations')->whereIn('migration', $neu->all())->delete();

        $this->rolle(1, 'dev');
        $this->rolle(2, 'admin');
        $this->rolle(3, 'user');

        $jetzt = now();
        foreach ([
            [21, 1, 'Anna', null], [22, 1, 'Bernd', $jetzt], [23, 2, 'Clara', null],
            [24, 3, 'Dirk', null], [25, 1, 'Emil', null], [26, null, 'Frida', null],
        ] as [$id, $rolle, $name, $geloescht]) {
            DB::table('users')->insert([
                'id' => $id, 'role_id' => $rolle, 'name' => $name, 'email' => strtolower($name).'@firma.de', 'email_verified_at' => $jetzt,
                'password' => Hash::make('password'), 'deleted_at' => $geloescht, 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }

        foreach ([1, 2] as $team) {
            DB::table('teams')->insert(['id' => $team, 'name' => 'Team '.$team, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }
        foreach ([[1, 21, 1], [1, 24, 3], [2, 23, 1], [2, 25, 1]] as [$team, $user, $rolle]) {
            DB::table('team_user')->insert(['team_id' => $team, 'user_id' => $user, 'role_id' => $rolle, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }

        $this->assertSame(3, DB::table('users')->where('role_id', 1)->count(), 'Aufbau: drei Nutzer mit Rolle dev');
    }

    /** @return array<int, int|null> Nutzer-ID → role_id */
    private function nutzerRollen(): array
    {
        return DB::table('users')->orderBy('id')->pluck('role_id', 'id')->map(fn ($r): ?int => $r === null ? null : (int) $r)->all();
    }

    /** @return array<string, int> "team/nutzer" → role_id */
    private function teamRollen(): array
    {
        $ergebnis = [];
        foreach (DB::table('team_user')->orderBy('id')->get() as $z) {
            $ergebnis[$z->team_id.'/'.$z->user_id] = (int) $z->role_id;
        }

        return $ergebnis;
    }

    // AK5
    public function test_die_migration_stellt_alle_dev_nutzer_auf_admin_um(): void
    {
        $this->standVorDemUpdate();

        $this->paketMigrationenAusfuehren();

        $rollen = $this->nutzerRollen();
        $this->assertSame(2, $rollen[21]);
        $this->assertSame(2, $rollen[25]);
        $this->assertSame(0, DB::table('users')->where('role_id', 1)->count());
    }

    // AK5
    public function test_die_migration_stellt_auch_weich_geloeschte_dev_nutzer_um(): void
    {
        $this->standVorDemUpdate();
        $this->assertNotNull(DB::table('users')->where('id', 22)->value('deleted_at'));

        $this->paketMigrationenAusfuehren();

        $this->assertSame(2, (int) DB::table('users')->where('id', 22)->value('role_id'));
        $this->assertNotNull(DB::table('users')->where('id', 22)->value('deleted_at'), 'der Nutzer bleibt gelöscht');
    }

    // AK5
    public function test_die_migration_stellt_team_user_role_id_von_dev_auf_admin_um(): void
    {
        $this->standVorDemUpdate();
        $this->assertSame(['1/21' => 1, '1/24' => 3, '2/23' => 1, '2/25' => 1], $this->teamRollen());

        $this->paketMigrationenAusfuehren();

        $this->assertSame(['1/21' => 2, '1/24' => 3, '2/23' => 2, '2/25' => 2], $this->teamRollen());
    }

    // AK5
    public function test_die_migration_laesst_andere_nutzer_und_alle_uebrigen_spalten_unveraendert(): void
    {
        $this->standVorDemUpdate();
        $vorher = DB::table('users')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all();

        $this->paketMigrationenAusfuehren();

        $this->assertSame(2, (int) DB::table('users')->where('id', 21)->value('role_id'), 'Gegenprobe: die Migration ist gelaufen');
        $nachher = DB::table('users')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all();
        $this->assertCount(count($vorher), $nachher);
        foreach ($vorher as $i => $zeile) {
            if (in_array((int) $zeile['id'], [21, 22, 25], true)) {
                $zeile['role_id'] = 2;
            }
            $this->assertEquals($zeile, $nachher[$i], 'Nutzer '.$zeile['id']);
        }
        $this->assertSame(2, $this->nutzerRollen()[23]);
        $this->assertSame(3, $this->nutzerRollen()[24]);
        $this->assertNull($this->nutzerRollen()[26]);
    }

    // AK5
    public function test_die_migration_loescht_keine_rolle_die_rolle_dev_bleibt_stehen(): void
    {
        $this->standVorDemUpdate();
        $vorher = DB::table('roles')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all();

        $this->paketMigrationenAusfuehren();

        $this->assertSame(2, (int) DB::table('users')->where('id', 21)->value('role_id'), 'Gegenprobe: die Migration ist gelaufen');
        $this->assertSame($vorher, DB::table('roles')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all());
        $this->assertSame(1, DB::table('roles')->where('role', 'dev')->count());
    }

    // AK5
    public function test_ohne_rolle_admin_aendert_die_migration_nichts(): void
    {
        $this->standVorDemUpdate();
        DB::table('users')->where('id', 23)->update(['role_id' => 3]);
        DB::table('team_user')->where('user_id', 23)->update(['role_id' => 3]);
        DB::table('roles')->where('role', 'admin')->delete();
        $nutzer = $this->nutzerRollen();
        $teams = $this->teamRollen();
        $rollen = DB::table('roles')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all();

        $this->paketMigrationenAusfuehren();

        $this->assertSame(1, DB::table('migrations')->whereNotIn('migration', self::BEKANNTE_MIGRATIONEN)->count(), 'Gegenprobe: die Migration ist gelaufen');
        $this->assertSame($nutzer, $this->nutzerRollen());
        $this->assertSame($teams, $this->teamRollen());
        $this->assertSame($rollen, DB::table('roles')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all());
    }

    // AK5
    public function test_mit_nur_geloeschter_rolle_admin_aendert_die_migration_nichts(): void
    {
        $this->standVorDemUpdate();
        DB::table('roles')->where('role', 'admin')->update(['deleted_at' => now()]);
        DB::table('users')->where('id', 23)->update(['role_id' => 3]);
        DB::table('team_user')->where('user_id', 23)->update(['role_id' => 3]);
        $nutzer = $this->nutzerRollen();
        $teams = $this->teamRollen();

        $this->paketMigrationenAusfuehren();

        $this->assertSame(1, DB::table('migrations')->whereNotIn('migration', self::BEKANNTE_MIGRATIONEN)->count(), 'Gegenprobe: die Migration ist gelaufen');
        $this->assertSame($nutzer, $this->nutzerRollen());
        $this->assertSame($teams, $this->teamRollen());
    }

    // Randfall 1
    public function test_bei_mehreren_rollen_admin_nimmt_die_migration_die_nicht_geloeschte_mit_der_kleinsten_id(): void
    {
        $this->standVorDemUpdate();
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['role']);
        });
        // Rolle 2 (admin) wird gelöscht und hat die kleinste ID; 5 und 6 sind nicht gelöscht
        DB::table('roles')->where('id', 2)->update(['deleted_at' => now()]);
        DB::table('users')->where('id', 23)->update(['role_id' => 3]);
        DB::table('team_user')->where('user_id', 23)->update(['role_id' => 3]);
        $this->rolle(6, 'admin');
        $this->rolle(5, 'admin');

        $this->paketMigrationenAusfuehren();

        $rollen = $this->nutzerRollen();
        $this->assertSame(5, $rollen[21]);
        $this->assertSame(5, $rollen[22]);
        $this->assertSame(5, $rollen[25]);
        $this->assertSame(['1/21' => 5, '1/24' => 3, '2/23' => 3, '2/25' => 5], $this->teamRollen());
    }

    // AK5
    public function test_das_zurueckrollen_macht_nichts(): void
    {
        $this->standVorDemUpdate();
        $this->paketMigrationenAusfuehren();
        $this->assertSame(2, $this->nutzerRollen()[21], 'Gegenprobe: die Migration ist gelaufen');
        $nutzer = $this->nutzerRollen();
        $teams = $this->teamRollen();
        $rollen = DB::table('roles')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all();

        $this->paketMigrationenZurueckrollen();

        $this->assertSame(0, DB::table('migrations')->whereNotIn('migration', self::BEKANNTE_MIGRATIONEN)->count(), 'Aufbau: die neue Migration wurde zurückgerollt');
        $this->assertSame($nutzer, $this->nutzerRollen());
        $this->assertSame($teams, $this->teamRollen());
        $this->assertSame($rollen, DB::table('roles')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all());
    }

    /**
     * Installation, die das Paket schon nutzt, aber ohne `roles`/`team_user` (z. B. Host mit eigenem Rollenmodell):
     * nur `users`, alle bisherigen Paket-Migrationen als gelaufen eingetragen.
     */
    private function installationOhneRollenTabellen(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        });
        Artisan::call('migrate:install');
        foreach (self::BEKANNTE_MIGRATIONEN as $migration) {
            DB::table('migrations')->insert(['migration' => $migration, 'batch' => 1]);
        }
        DB::table('users')->insert(['id' => 1, 'role_id' => 1, 'name' => 'A', 'email' => 'a@firma.de', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    // AK5
    public function test_die_migration_ohne_tabellen_roles_und_team_user_bricht_nicht_ab_und_aendert_nichts(): void
    {
        $this->installationOhneRollenTabellen();

        $this->paketMigrationenAusfuehren();

        $this->assertSame(1, DB::table('migrations')->whereNotIn('migration', self::BEKANNTE_MIGRATIONEN)->count(), 'Gegenprobe: die neue Migration ist gelaufen');
        $this->assertSame(1, (int) DB::table('users')->where('id', 1)->value('role_id'));
    }

    // AK5
    public function test_die_migration_ohne_tabellen_team_user_stellt_die_nutzer_trotzdem_um(): void
    {
        $this->installationOhneRollenTabellen();
        Schema::create('roles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('role')->unique();
            $table->string('role_display');
            $table->timestamps();
            $table->softDeletes();
        });
        $this->rolle(1, 'dev');
        $this->rolle(2, 'admin');

        $this->paketMigrationenAusfuehren();

        $this->assertSame(2, (int) DB::table('users')->where('id', 1)->value('role_id'));
    }

    // ---------------------------------------------------------------- AK6: Doku

    private function readme(): string
    {
        $inhalt = file_get_contents(dirname(__DIR__, 2).'/README.md');
        $this->assertIsString($inhalt);

        return $inhalt;
    }

    /** Text von der Überschrift bis zur nächsten Überschrift gleicher oder höherer Ebene. */
    private function abschnitt(string $text, string $ueberschrift): string
    {
        $ebene = strlen($ueberschrift) - strlen(ltrim($ueberschrift, '#'));
        $start = strpos($text, "\n".$ueberschrift);
        $this->assertNotFalse($start, "Überschrift fehlt: {$ueberschrift}");
        $rest = substr($text, $start + 1 + strlen($ueberschrift));
        $ende = preg_match('/\n#{1,'.$ebene.'} /', $rest, $treffer, PREG_OFFSET_CAPTURE) === 1 ? $treffer[0][1] : strlen($rest);

        return substr($rest, 0, $ende);
    }

    // AK6
    public function test_die_readme_beschreibt_nur_den_schluessel_admin_als_privileg(): void
    {
        $abschnitt = $this->abschnitt($this->readme(), '### Privilegierte Rollen');

        $this->assertStringContainsString('| Admin |', $abschnitt, 'Gegenprobe: die Tabelle der Privilegien ist gelesen');
        $this->assertMatchesRegularExpression('/Schlüssel `admin`/', $abschnitt);
        $this->assertStringNotContainsString('| Dev |', $abschnitt);
        $this->assertDoesNotMatchRegularExpression('/ID `[12]`/', $abschnitt, 'Privileg hängt nicht an einer ID');
        $this->assertDoesNotMatchRegularExpression('/\*\*und\*\* Schlüssel/', $abschnitt);
    }

    // AK6
    public function test_die_readme_hat_upgrade_hinweise_zur_migration_dev_nach_admin(): void
    {
        $readme = $this->readme();
        $this->assertStringContainsString('## Upgrade-Hinweise', $readme, 'Gegenprobe: Abschnitt Upgrade-Hinweise existiert');
        $abschnitt = $this->abschnitt($readme, '## Upgrade-Hinweise (nach 3.0.0, unveröffentlicht)');

        $this->assertMatchesRegularExpression('/dev.{0,200}admin/su', $abschnitt, 'Migration stellt dev-Nutzer auf admin um');
        $this->assertMatchesRegularExpression('/Rolle `dev`.{0,200}(bleibt|gewöhnliche Rolle)/su', $abschnitt);
        $this->assertMatchesRegularExpression('/gelöscht werden/u', $abschnitt, 'Rolle dev kann gelöscht werden');
        $this->assertStringContainsString('`isDev`', $abschnitt);
        $this->assertMatchesRegularExpression('/`isDev`.{0,200}`isAdmin`/su', $abschnitt, 'isDev wirkt wie isAdmin');
        $this->assertStringContainsString('hasRole:dev', $abschnitt);
        $this->assertStringContainsString('permissions_middleware', $abschnitt);
    }

    // AK6
    public function test_der_kommentar_zu_impersonate_enabled_nennt_nur_noch_admin(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/src/Config/config.php');
        $this->assertIsString($config);

        $this->assertSame(1, preg_match("/\n([^\n]*\/\/[^\n]*)\n\s*'impersonate_enabled'/", $config, $treffer), 'Gegenprobe: der Kommentar ist gefunden');
        $this->assertMatchesRegularExpression('/admin/i', $treffer[1]);
        $this->assertDoesNotMatchRegularExpression('/dev/i', $treffer[1]);
    }
}
