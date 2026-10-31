<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ITHilbert\UserAuth\Database\Seeders\DatabaseSeeder;
use ITHilbert\UserAuth\Database\Seeders\PermissionsGroupsTableSeeder;
use ITHilbert\UserAuth\Database\Seeders\PermissionsTableSeeder;
use ITHilbert\UserAuth\Database\Seeders\RolesTableSeeder;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Spec userauth-seeder (Dashboard #260): Seeder lassen Einträge des Hosts stehen.
 */
final class SeederHostEintraegeTest extends TestCase
{
    private const ROLLEN = [
        ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer'],
        ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin'],
        ['id' => 3, 'role' => 'user', 'role_display' => 'Anwender'],
    ];

    private const GRUPPEN = [
        ['id' => 1, 'group_name' => 'user', 'group_display' => 'Benutzer', 'created_at' => '2020-08-10 13:25:31', 'updated_at' => '2020-08-12 12:30:58'],
        ['id' => 2, 'group_name' => 'role', 'group_display' => 'Rollen', 'created_at' => '2020-08-12 12:31:35', 'updated_at' => '2020-08-12 12:31:35'],
    ];

    private const RECHTE = [
        ['id' => 1, 'permission' => 'user_create', 'permission_display' => 'Benutzer erstellen', 'group_id' => 1, 'crud' => 'create', 'created_at' => '2020-08-10 13:25:31', 'updated_at' => '2020-08-12 12:30:58'],
        ['id' => 2, 'permission' => 'user_read', 'permission_display' => 'Benutzer lesen', 'group_id' => 1, 'crud' => 'read', 'created_at' => '2020-08-10 13:25:31', 'updated_at' => '2020-08-12 12:30:58'],
        ['id' => 3, 'permission' => 'user_edit', 'permission_display' => 'Benutzer ändern', 'group_id' => 1, 'crud' => 'edit', 'created_at' => '2020-08-10 13:25:31', 'updated_at' => '2020-08-12 12:30:58'],
        ['id' => 4, 'permission' => 'user_delete', 'permission_display' => 'Benutzer delete', 'group_id' => 1, 'crud' => 'delete', 'created_at' => '2020-08-10 13:25:31', 'updated_at' => '2020-08-12 12:30:58'],
        ['id' => 5, 'permission' => 'role_create', 'permission_display' => 'Rollen erstellen', 'group_id' => 2, 'crud' => 'create', 'created_at' => '2020-08-12 12:31:35', 'updated_at' => '2020-08-12 12:31:35'],
        ['id' => 6, 'permission' => 'role_read', 'permission_display' => 'Rollen lesen', 'group_id' => 2, 'crud' => 'read', 'created_at' => '2020-08-12 12:31:35', 'updated_at' => '2020-08-12 12:31:35'],
        ['id' => 7, 'permission' => 'role_edit', 'permission_display' => 'Rollen ändern', 'group_id' => 2, 'crud' => 'edit', 'created_at' => '2020-08-12 12:31:35', 'updated_at' => '2020-08-12 12:31:35'],
        ['id' => 8, 'permission' => 'role_delete', 'permission_display' => 'Rollen delete', 'group_id' => 2, 'crud' => 'delete', 'created_at' => '2020-08-12 12:31:35', 'updated_at' => '2020-08-12 12:31:35'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('permissions_groups');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        Schema::create('roles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('role')->unique();
            $t->string('role_display');
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('permissions_groups', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('group_name')->unique();
            $t->string('group_display');
            $t->integer('is_group')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('permission')->unique();
            $t->string('permission_display');
            $t->bigInteger('group_id');
            $t->string('crud', 10);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('role_permission', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $t->unsignedBigInteger('permission_id');
            $t->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $t->unique(['role_id', 'permission_id']);
        });
        Schema::create('users', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('role_id')->nullable();
            $t->string('name');
            $t->string('email');
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->timestamps();
            $t->softDeletes();
        });
    }

    private function seedAlle(): void
    {
        $this->seed(RolesTableSeeder::class);
        $this->seed(PermissionsGroupsTableSeeder::class);
        $this->seed(PermissionsTableSeeder::class);
    }

    /** @return array<string, mixed> */
    private function hostRolle(): array
    {
        return ['id' => 10, 'role' => 'kunde_host', 'role_display' => 'Kunde (Host)', 'created_at' => '2024-01-02 03:04:05', 'updated_at' => '2024-02-03 04:05:06', 'deleted_at' => null];
    }

    /** @return array<string, mixed> */
    private function hostGruppe(): array
    {
        return ['id' => 10, 'group_name' => 'projekt', 'group_display' => 'Projekte', 'is_group' => 1, 'created_at' => '2024-01-02 03:04:05', 'updated_at' => '2024-02-03 04:05:06', 'deleted_at' => null];
    }

    /** @return array<string, mixed> */
    private function hostRecht(): array
    {
        return ['id' => 10, 'permission' => 'projekt_read', 'permission_display' => 'Projekte lesen', 'group_id' => 10, 'crud' => 'read', 'created_at' => '2024-01-02 03:04:05', 'updated_at' => '2024-02-03 04:05:06', 'deleted_at' => null];
    }

    private function hostEintraegeAnlegen(): void
    {
        DB::table('roles')->insert($this->hostRolle());
        DB::table('permissions_groups')->insert($this->hostGruppe());
        DB::table('permissions')->insert($this->hostRecht());
        DB::table('role_permission')->insert(['role_id' => 10, 'permission_id' => 10]);
    }

    /** @return array<string, mixed>|null */
    private function zeile(string $tabelle, string $spalte, string $wert): ?array
    {
        $z = DB::table($tabelle)->where($spalte, $wert)->first();

        return $z === null ? null : (array) $z;
    }

    /** @return array<int, array<string, mixed>> */
    private function schnappschuss(): array
    {
        return [
            'roles' => DB::table('roles')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'groups' => DB::table('permissions_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'permissions' => DB::table('permissions')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'pivot' => DB::table('role_permission')->orderBy('role_id')->orderBy('permission_id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    // AK1
    public function test_rolle_des_hosts_bleibt_nach_dem_roles_seeder_unveraendert(): void
    {
        $this->hostEintraegeAnlegen();
        $this->assertNotNull($this->zeile('roles', 'role', 'kunde_host'));

        $this->seed(RolesTableSeeder::class);

        $this->assertNotNull($this->zeile('roles', 'role', 'dev'), 'Seeder hat keine Paket-Rolle angelegt');
        $this->assertSame($this->hostRolle(), $this->zeile('roles', 'role', 'kunde_host'));
    }

    // AK1
    public function test_gruppe_des_hosts_bleibt_nach_dem_groups_seeder_unveraendert(): void
    {
        $this->hostEintraegeAnlegen();
        $this->assertNotNull($this->zeile('permissions_groups', 'group_name', 'projekt'));

        $this->seed(PermissionsGroupsTableSeeder::class);

        $this->assertNotNull($this->zeile('permissions_groups', 'group_name', 'user'), 'Seeder hat keine Paket-Gruppe angelegt');
        $this->assertSame($this->hostGruppe(), $this->zeile('permissions_groups', 'group_name', 'projekt'));
    }

    // AK1
    public function test_recht_des_hosts_bleibt_nach_dem_permissions_seeder_unveraendert(): void
    {
        $this->hostEintraegeAnlegen();
        $this->assertNotNull($this->zeile('permissions', 'permission', 'projekt_read'));

        $this->seedAlle();

        $this->assertNotNull($this->zeile('permissions', 'permission', 'user_create'), 'Seeder hat kein Paket-Recht angelegt');
        $this->assertSame($this->hostRecht(), $this->zeile('permissions', 'permission', 'projekt_read'));
    }

    // AK1
    public function test_zuordnung_rolle_recht_des_hosts_bleibt_erhalten(): void
    {
        $this->hostEintraegeAnlegen();
        $this->assertSame(1, DB::table('role_permission')->where(['role_id' => 10, 'permission_id' => 10])->count());

        $this->seedAlle();

        $this->assertSame(8, DB::table('permissions')->whereIn('permission', array_column(self::RECHTE, 'permission'))->count(), 'Seeder hat nichts angelegt');
        $this->assertSame(1, DB::table('role_permission')->where(['role_id' => 10, 'permission_id' => 10])->count());
        $this->assertSame(1, DB::table('role_permission')->count());
    }

    // AK1
    public function test_databaseseeder_laesst_eintraege_und_zuordnung_des_hosts_stehen(): void
    {
        $this->hostEintraegeAnlegen();
        $this->assertNotNull($this->zeile('roles', 'role', 'kunde_host'));

        $this->seed(DatabaseSeeder::class);

        $this->assertNotNull($this->zeile('roles', 'role', 'dev'), 'DatabaseSeeder hat nichts angelegt');
        $this->assertSame($this->hostRolle(), $this->zeile('roles', 'role', 'kunde_host'));
        $this->assertSame($this->hostGruppe(), $this->zeile('permissions_groups', 'group_name', 'projekt'));
        $this->assertSame($this->hostRecht(), $this->zeile('permissions', 'permission', 'projekt_read'));
        $this->assertSame(1, DB::table('role_permission')->where(['role_id' => 10, 'permission_id' => 10])->count());
    }

    // AK2
    public function test_leere_datenbank_bekommt_die_drei_rollen_mit_festen_ids(): void
    {
        $this->assertSame(0, DB::table('roles')->count());

        $this->seed(RolesTableSeeder::class);

        $rollen = DB::table('roles')->orderBy('id')->get()->map(fn ($r) => [
            'id' => (int) $r->id, 'role' => $r->role, 'role_display' => $r->role_display,
        ])->all();
        $this->assertSame(self::ROLLEN, $rollen);
    }

    // AK2
    public function test_leere_datenbank_bekommt_die_zwei_gruppen_mit_festen_ids_und_spalten(): void
    {
        $this->assertSame(0, DB::table('permissions_groups')->count());

        $this->seed(PermissionsGroupsTableSeeder::class);

        $gruppen = DB::table('permissions_groups')->orderBy('id')->get()->map(fn ($r) => [
            'id' => (int) $r->id, 'group_name' => $r->group_name, 'group_display' => $r->group_display,
            'created_at' => $r->created_at, 'updated_at' => $r->updated_at,
        ])->all();
        $this->assertSame(self::GRUPPEN, $gruppen);
        $this->assertSame(0, DB::table('permissions_groups')->whereNotNull('deleted_at')->count());
    }

    // AK2
    public function test_leere_datenbank_bekommt_die_acht_rechte_mit_gruppenzuordnung(): void
    {
        $this->assertSame(0, DB::table('permissions')->count());

        $this->seedAlle();

        $rechte = DB::table('permissions')->orderBy('id')->get()->map(fn ($r) => [
            'id' => (int) $r->id, 'permission' => $r->permission, 'permission_display' => $r->permission_display,
            'group_id' => (int) $r->group_id, 'crud' => $r->crud,
            'created_at' => $r->created_at, 'updated_at' => $r->updated_at,
        ])->all();
        $this->assertSame(self::RECHTE, $rechte);
        $this->assertSame(0, DB::table('permissions')->whereNotNull('deleted_at')->count());
    }

    // AK3
    public function test_zweiter_lauf_aendert_anzahl_und_inhalt_nicht(): void
    {
        $this->seedAlle();
        $erster = $this->schnappschuss();
        $this->assertCount(3, $erster['roles']);
        $this->assertCount(2, $erster['groups']);
        $this->assertCount(8, $erster['permissions']);

        $this->seedAlle();

        $this->assertSame($erster, $this->schnappschuss());
    }

    // AK3
    public function test_zweiter_lauf_mit_hosteintraegen_erzeugt_keine_duplikate_und_aendert_nichts(): void
    {
        $this->hostEintraegeAnlegen();
        $this->seedAlle();
        $erster = $this->schnappschuss();
        $this->assertCount(4, $erster['roles']);
        $this->assertCount(3, $erster['groups']);
        $this->assertCount(9, $erster['permissions']);

        $this->seedAlle();

        $this->assertSame($erster, $this->schnappschuss());
    }

    // AK3
    public function test_zweiter_lauf_ueber_databaseseeder_wirft_keinen_fehler(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(3, DB::table('roles')->count());

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, DB::table('roles')->count());
        $this->assertSame(2, DB::table('permissions_groups')->count());
        $this->assertSame(8, DB::table('permissions')->count());
    }

    // AK4
    public function test_vorhandene_paket_rolle_bleibt_unveraendert_ohne_zweiten_eintrag(): void
    {
        DB::table('roles')->insert(['id' => 7, 'role' => 'user', 'role_display' => 'Mitarbeiter', 'created_at' => '2024-05-05 05:05:05', 'updated_at' => '2024-05-05 05:05:05', 'deleted_at' => null]);
        $vorher = $this->zeile('roles', 'role', 'user');

        $this->seed(RolesTableSeeder::class);

        $this->assertNotNull($this->zeile('roles', 'role', 'dev'), 'Seeder hat fehlende Rollen nicht angelegt');
        $this->assertSame($vorher, $this->zeile('roles', 'role', 'user'));
        $this->assertSame(1, DB::table('roles')->where('role', 'user')->count());
    }

    // AK4
    public function test_vorhandene_paket_gruppe_bleibt_unveraendert_ohne_zweiten_eintrag(): void
    {
        DB::table('permissions_groups')->insert(['id' => 9, 'group_name' => 'user', 'group_display' => 'Mitarbeiter', 'is_group' => 0, 'created_at' => '2024-05-05 05:05:05', 'updated_at' => '2024-05-05 05:05:05', 'deleted_at' => null]);
        $vorher = $this->zeile('permissions_groups', 'group_name', 'user');

        $this->seed(PermissionsGroupsTableSeeder::class);

        $this->assertNotNull($this->zeile('permissions_groups', 'group_name', 'role'), 'Seeder hat fehlende Gruppen nicht angelegt');
        $this->assertSame($vorher, $this->zeile('permissions_groups', 'group_name', 'user'));
        $this->assertSame(1, DB::table('permissions_groups')->where('group_name', 'user')->count());
    }

    // AK4
    public function test_vorhandenes_paket_recht_bleibt_unveraendert_ohne_zweiten_eintrag(): void
    {
        DB::table('permissions')->insert(['id' => 20, 'permission' => 'user_read', 'permission_display' => 'Eigene Anzeige', 'group_id' => 99, 'crud' => 'read', 'created_at' => '2024-05-05 05:05:05', 'updated_at' => '2024-05-05 05:05:05', 'deleted_at' => null]);
        $vorher = $this->zeile('permissions', 'permission', 'user_read');

        $this->seedAlle();

        $this->assertNotNull($this->zeile('permissions', 'permission', 'user_create'), 'Seeder hat fehlende Rechte nicht angelegt');
        $this->assertSame($vorher, $this->zeile('permissions', 'permission', 'user_read'));
        $this->assertSame(1, DB::table('permissions')->where('permission', 'user_read')->count());
    }

    // Randfall 1
    public function test_belegte_rollen_id_fuehrt_zu_freier_id_fuer_user_ohne_fehler(): void
    {
        DB::table('roles')->insert(['id' => 3, 'role' => 'kunde', 'role_display' => 'Kunde', 'created_at' => null, 'updated_at' => null, 'deleted_at' => null]);

        $this->seed(RolesTableSeeder::class);

        $kunde = $this->zeile('roles', 'role', 'kunde');
        $this->assertNotNull($kunde, 'Host-Rolle kunde fehlt nach dem Seeden');
        $this->assertSame(3, (int) $kunde['id']);
        $this->assertSame('Kunde', $kunde['role_display']);
        $user = $this->zeile('roles', 'role', 'user');
        $this->assertNotNull($user, 'Paket-Rolle user fehlt');
        $this->assertNotSame(3, (int) $user['id']);
        $this->assertSame('Anwender', $user['role_display']);
        $this->assertSame(4, DB::table('roles')->count());
    }

    // Randfall 1
    public function test_belegte_gruppen_und_rechte_ids_fuehren_zu_freien_ids_ohne_fehler(): void
    {
        DB::table('permissions_groups')->insert(['id' => 1, 'group_name' => 'kunden', 'group_display' => 'Kunden', 'is_group' => 0, 'created_at' => null, 'updated_at' => null, 'deleted_at' => null]);
        DB::table('permissions')->insert(['id' => 1, 'permission' => 'kunden_read', 'permission_display' => 'Kunden lesen', 'group_id' => 1, 'crud' => 'read', 'created_at' => null, 'updated_at' => null, 'deleted_at' => null]);

        $this->seedAlle();

        $gruppe = $this->zeile('permissions_groups', 'group_name', 'kunden');
        $recht = $this->zeile('permissions', 'permission', 'kunden_read');
        $this->assertNotNull($gruppe, 'Host-Gruppe kunden fehlt nach dem Seeden');
        $this->assertNotNull($recht, 'Host-Recht kunden_read fehlt nach dem Seeden');
        $this->assertSame(1, (int) $gruppe['id']);
        $this->assertSame(1, (int) $recht['id']);
        $this->assertSame(1, (int) $recht['group_id']);
        $this->assertNotNull($this->zeile('permissions_groups', 'group_name', 'user'), 'Paket-Gruppe user fehlt');
        $this->assertNotNull($this->zeile('permissions', 'permission', 'user_create'), 'Paket-Recht user_create fehlt');
        foreach (['user_create', 'user_read', 'user_edit', 'user_delete', 'role_create', 'role_read', 'role_edit', 'role_delete'] as $name) {
            $this->assertNotNull($this->zeile('permissions', 'permission', $name), "Paket-Recht {$name} fehlt");
        }
        $this->assertSame(9, DB::table('permissions')->count());
    }

    // Randfall 2
    public function test_rechte_werden_der_gruppe_ueber_den_gruppennamen_zugeordnet(): void
    {
        DB::table('permissions_groups')->insert(['id' => 1, 'group_name' => 'kunden', 'group_display' => 'Kunden', 'is_group' => 0, 'created_at' => null, 'updated_at' => null, 'deleted_at' => null]);

        $this->seedAlle();

        $userGruppe = (int) $this->zeile('permissions_groups', 'group_name', 'user')['id'];
        $roleGruppe = (int) $this->zeile('permissions_groups', 'group_name', 'role')['id'];
        $this->assertNotSame(1, $userGruppe);
        foreach (['user_create', 'user_read', 'user_edit', 'user_delete'] as $recht) {
            $this->assertSame($userGruppe, (int) $this->zeile('permissions', 'permission', $recht)['group_id'], $recht);
        }
        foreach (['role_create', 'role_read', 'role_edit', 'role_delete'] as $recht) {
            $this->assertSame($roleGruppe, (int) $this->zeile('permissions', 'permission', $recht)['group_id'], $recht);
        }
    }

    // Randfall 2
    public function test_rechte_folgen_einer_vorhandenen_gruppe_mit_anderer_id(): void
    {
        DB::table('permissions_groups')->insert(['id' => 9, 'group_name' => 'user', 'group_display' => 'Mitarbeiter', 'is_group' => 0, 'created_at' => null, 'updated_at' => null, 'deleted_at' => null]);

        $this->seedAlle();

        foreach (['user_create', 'user_read', 'user_edit', 'user_delete'] as $recht) {
            $this->assertSame(9, (int) $this->zeile('permissions', 'permission', $recht)['group_id'], $recht);
        }
    }

    // Randfall 3
    public function test_soft_geloeschte_paket_rolle_gilt_als_vorhanden(): void
    {
        DB::table('roles')->insert(['id' => 1, 'role' => 'dev', 'role_display' => 'Alt', 'created_at' => null, 'updated_at' => null, 'deleted_at' => '2024-03-03 03:03:03']);
        $vorher = $this->zeile('roles', 'role', 'dev');

        $this->seed(RolesTableSeeder::class);

        $this->assertNotNull($this->zeile('roles', 'role', 'admin'), 'Seeder hat fehlende Rollen nicht angelegt');
        $this->assertSame($vorher, $this->zeile('roles', 'role', 'dev'));
        $this->assertSame(1, DB::table('roles')->where('role', 'dev')->count());
    }

    // Randfall 3
    public function test_soft_geloeschte_paket_gruppe_und_recht_gelten_als_vorhanden(): void
    {
        DB::table('permissions_groups')->insert(['id' => 1, 'group_name' => 'user', 'group_display' => 'Alt', 'is_group' => 0, 'created_at' => null, 'updated_at' => null, 'deleted_at' => '2024-03-03 03:03:03']);
        DB::table('permissions')->insert(['id' => 1, 'permission' => 'user_create', 'permission_display' => 'Alt', 'group_id' => 1, 'crud' => 'create', 'created_at' => null, 'updated_at' => null, 'deleted_at' => '2024-03-03 03:03:03']);
        $gruppe = $this->zeile('permissions_groups', 'group_name', 'user');
        $recht = $this->zeile('permissions', 'permission', 'user_create');

        $this->seedAlle();

        $this->assertNotNull($this->zeile('permissions', 'permission', 'user_read'), 'Seeder hat fehlende Rechte nicht angelegt');
        $this->assertSame($gruppe, $this->zeile('permissions_groups', 'group_name', 'user'));
        $this->assertSame($recht, $this->zeile('permissions', 'permission', 'user_create'));
        $this->assertSame(1, DB::table('permissions_groups')->where('group_name', 'user')->count());
        $this->assertSame(1, DB::table('permissions')->where('permission', 'user_create')->count());
    }

    // Randfall 4
    public function test_permissions_seeder_allein_legt_bei_leerer_gruppentabelle_die_paket_gruppen_an(): void
    {
        $this->assertSame(0, DB::table('permissions_groups')->count());

        $this->seed(PermissionsTableSeeder::class);

        $this->assertSame(8, DB::table('permissions')->count(), 'Seeder hat die Rechte nicht angelegt');
        $gruppen = DB::table('permissions_groups')->orderBy('id')->get()->map(fn ($r) => [
            'id' => (int) $r->id, 'group_name' => $r->group_name, 'group_display' => $r->group_display,
            'created_at' => $r->created_at, 'updated_at' => $r->updated_at,
        ])->all();
        $this->assertSame(self::GRUPPEN, $gruppen);
    }

    // Randfall 4
    public function test_permissions_seeder_allein_ordnet_die_rechte_bei_leerer_gruppentabelle_den_neuen_gruppen_zu(): void
    {
        $this->seed(PermissionsTableSeeder::class);

        $this->assertSame(8, DB::table('permissions')->count(), 'Seeder hat die Rechte nicht angelegt');
        $userGruppe = (int) $this->zeile('permissions_groups', 'group_name', 'user')['id'];
        $roleGruppe = (int) $this->zeile('permissions_groups', 'group_name', 'role')['id'];
        foreach (['user_create', 'user_read', 'user_edit', 'user_delete'] as $recht) {
            $this->assertSame($userGruppe, (int) $this->zeile('permissions', 'permission', $recht)['group_id'], $recht);
        }
        foreach (['role_create', 'role_read', 'role_edit', 'role_delete'] as $recht) {
            $this->assertSame($roleGruppe, (int) $this->zeile('permissions', 'permission', $recht)['group_id'], $recht);
        }
    }

    // Randfall 4
    public function test_permissions_seeder_allein_mit_nur_host_gruppen_laesst_diese_unveraendert_und_legt_paket_gruppen_an(): void
    {
        $this->hostEintraegeAnlegen();
        $this->assertSame($this->hostGruppe(), $this->zeile('permissions_groups', 'group_name', 'projekt'));

        $this->seed(PermissionsTableSeeder::class);

        $this->assertNotNull($this->zeile('permissions', 'permission', 'user_create'), 'Seeder hat die Rechte nicht angelegt');
        $this->assertSame($this->hostGruppe(), $this->zeile('permissions_groups', 'group_name', 'projekt'));
        $this->assertNotNull($this->zeile('permissions_groups', 'group_name', 'user'), 'Paket-Gruppe user fehlt');
        $this->assertNotNull($this->zeile('permissions_groups', 'group_name', 'role'), 'Paket-Gruppe role fehlt');
        $this->assertSame(3, DB::table('permissions_groups')->count());
        $this->assertSame($this->hostRecht(), $this->zeile('permissions', 'permission', 'projekt_read'));
        $this->assertSame(9, DB::table('permissions')->count());
        $this->assertSame(1, DB::table('role_permission')->where(['role_id' => 10, 'permission_id' => 10])->count());
    }

    // Randfall 4
    public function test_permissions_seeder_allein_ordnet_rechte_den_paket_gruppen_zu_wenn_nur_host_gruppen_da_sind(): void
    {
        DB::table('permissions_groups')->insert(['id' => 1, 'group_name' => 'kunden', 'group_display' => 'Kunden', 'is_group' => 0, 'created_at' => null, 'updated_at' => null, 'deleted_at' => null]);

        $this->seed(PermissionsTableSeeder::class);

        $this->assertNotNull($this->zeile('permissions', 'permission', 'user_create'), 'Seeder hat die Rechte nicht angelegt');
        $host = $this->zeile('permissions_groups', 'group_name', 'kunden');
        $this->assertSame(1, (int) $host['id']);
        $this->assertSame('Kunden', $host['group_display']);
        $userGruppe = (int) $this->zeile('permissions_groups', 'group_name', 'user')['id'];
        $roleGruppe = (int) $this->zeile('permissions_groups', 'group_name', 'role')['id'];
        $this->assertNotSame(1, $userGruppe);
        $this->assertNotSame(1, $roleGruppe);
        $this->assertSame($userGruppe, (int) $this->zeile('permissions', 'permission', 'user_read')['group_id']);
        $this->assertSame($roleGruppe, (int) $this->zeile('permissions', 'permission', 'role_read')['group_id']);
    }

    // Randfall 4
    public function test_permissions_seeder_allein_ueberschreibt_vorhandene_paket_gruppe_nicht_und_ergaenzt_die_fehlende(): void
    {
        DB::table('permissions_groups')->insert(['id' => 9, 'group_name' => 'user', 'group_display' => 'Mitarbeiter', 'is_group' => 0, 'created_at' => '2024-05-05 05:05:05', 'updated_at' => '2024-05-05 05:05:05', 'deleted_at' => null]);
        $vorher = $this->zeile('permissions_groups', 'group_name', 'user');

        $this->seed(PermissionsTableSeeder::class);

        $this->assertNotNull($this->zeile('permissions', 'permission', 'role_create'), 'Seeder hat die Rechte nicht angelegt');
        $this->assertNotNull($this->zeile('permissions_groups', 'group_name', 'role'), 'Fehlende Paket-Gruppe role wurde nicht angelegt');
        $this->assertSame($vorher, $this->zeile('permissions_groups', 'group_name', 'user'));
        $this->assertSame(1, DB::table('permissions_groups')->where('group_name', 'user')->count());
        $this->assertSame(9, (int) $this->zeile('permissions', 'permission', 'user_create')['group_id']);
    }

    // Randfall 4
    public function test_permissions_seeder_allein_zweimal_erzeugt_keine_doppelten_gruppen_oder_rechte(): void
    {
        $this->seed(PermissionsTableSeeder::class);
        $erster = $this->schnappschuss();
        $this->assertCount(2, $erster['groups']);
        $this->assertCount(8, $erster['permissions']);

        $this->seed(PermissionsTableSeeder::class);

        $this->assertSame($erster, $this->schnappschuss());
    }
}
