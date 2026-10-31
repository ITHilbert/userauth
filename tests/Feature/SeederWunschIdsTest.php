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
 * Spec userauth-seeder-wunsch-ids (Dashboard #319): freie Wunsch-ids zuerst.
 */
final class SeederWunschIdsTest extends TestCase
{
    private const ROLLEN = ['dev' => 1, 'admin' => 2, 'user' => 3];

    private const GRUPPEN = ['user' => 1, 'role' => 2];

    private const RECHTE = [
        'user_create' => 1, 'user_read' => 2, 'user_edit' => 3, 'user_delete' => 4,
        'role_create' => 5, 'role_read' => 6, 'role_edit' => 7, 'role_delete' => 8,
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

    /** Leert die Tabelle und setzt den Autoincrement-Zähler zurück (SQLite), sonst verfälscht er die vergebenen ids. */
    private function leere(string $tabelle): void
    {
        DB::table($tabelle)->delete();
        DB::table('sqlite_sequence')->where('name', $tabelle)->delete();
    }

    private function hostRolle(int $id, string $name): void
    {
        DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => 'Host '.$name, 'created_at' => '2024-01-02 03:04:05', 'updated_at' => '2024-02-03 04:05:06', 'deleted_at' => null]);
    }

    private function hostGruppe(int $id, string $name): void
    {
        DB::table('permissions_groups')->insert(['id' => $id, 'group_name' => $name, 'group_display' => 'Host '.$name, 'is_group' => 1, 'created_at' => '2024-01-02 03:04:05', 'updated_at' => '2024-02-03 04:05:06', 'deleted_at' => null]);
    }

    private function hostRecht(int $id, string $name): void
    {
        DB::table('permissions')->insert(['id' => $id, 'permission' => $name, 'permission_display' => 'Host '.$name, 'group_id' => 99, 'crud' => 'read', 'created_at' => '2024-01-02 03:04:05', 'updated_at' => '2024-02-03 04:05:06', 'deleted_at' => null]);
    }

    /** @return array<string, mixed>|null */
    private function zeile(string $tabelle, string $spalte, string $wert): ?array
    {
        $z = DB::table($tabelle)->where($spalte, $wert)->first();

        return $z === null ? null : (array) $z;
    }

    private function id(string $tabelle, string $spalte, string $wert): int
    {
        $z = $this->zeile($tabelle, $spalte, $wert);
        $this->assertNotNull($z, "{$tabelle}: {$wert} fehlt nach dem Seeden");

        return (int) $z['id'];
    }

    /** @return array<int, array<string, mixed>> */
    private function schnappschuss(): array
    {
        return [
            'roles' => DB::table('roles')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'groups' => DB::table('permissions_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'permissions' => DB::table('permissions')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    /**
     * Prüft die Regel aus AK1 für eine Tabelle: $belegt sind die Wunsch-ids (Werte von $wunsch), die der Host hält.
     *
     * @param  array<string, int>  $wunsch  Name => Wunsch-id der Paket-Einträge
     * @param  array<int, int>  $belegt
     */
    private function pruefeAk1(string $tabelle, string $spalte, array $wunsch, array $belegt, string $kontext): void
    {
        $alleWunsch = array_values($wunsch);
        $ausweicher = [];
        foreach ($wunsch as $name => $wunschId) {
            $id = $this->id($tabelle, $spalte, $name);
            if (in_array($wunschId, $belegt, true)) {
                $ausweicher[$name] = $id;
                $this->assertNotContains($id, $alleWunsch, "{$kontext}: {$name} wich auf eine Wunsch-id aus");
                $this->assertNotContains($id, $belegt, "{$kontext}: {$name} überschreibt einen Host-Eintrag");
            } else {
                $this->assertSame($wunschId, $id, "{$kontext}: {$name} hat seine freie Wunsch-id nicht bekommen");
            }
        }
        $this->assertSame(count($ausweicher), count(array_unique($ausweicher)), "{$kontext}: Ausweich-ids doppelt");
        $this->assertSame(count($wunsch) + count($belegt), DB::table($tabelle)->count(), "{$kontext}: Zeilenanzahl");
    }

    /** @return array<int, array<int, int>> alle Teilmengen */
    private function teilmengen(array $ids): array
    {
        $ergebnis = [];
        $n = count($ids);
        for ($m = 0; $m < (1 << $n); $m++) {
            $teil = [];
            foreach ($ids as $i => $id) {
                if ($m & (1 << $i)) {
                    $teil[] = $id;
                }
            }
            $ergebnis[] = $teil;
        }

        return $ergebnis;
    }

    // AK1
    public function test_ak1_rollen_freie_wunsch_ids_gehen_an_die_paket_rolle_fuer_jede_belegung(): void
    {
        foreach ($this->teilmengen([1, 2, 3]) as $belegt) {
            $this->leere('roles');
            foreach ($belegt as $id) {
                $this->hostRolle($id, "host_{$id}");
            }

            $this->seed(RolesTableSeeder::class);

            $this->pruefeAk1('roles', 'role', self::ROLLEN, $belegt, 'Rollen belegt='.implode(',', $belegt));
            foreach ($belegt as $id) {
                $this->assertSame('Host host_'.$id, DB::table('roles')->where('id', $id)->value('role_display'));
            }
        }
    }

    // AK1
    public function test_ak1_gruppen_freie_wunsch_ids_gehen_an_die_paket_gruppe_fuer_jede_belegung(): void
    {
        foreach ($this->teilmengen([1, 2]) as $belegt) {
            $this->leere('permissions_groups');
            foreach ($belegt as $id) {
                $this->hostGruppe($id, "host_{$id}");
            }

            $this->seed(PermissionsGroupsTableSeeder::class);

            $this->pruefeAk1('permissions_groups', 'group_name', self::GRUPPEN, $belegt, 'Gruppen belegt='.implode(',', $belegt));
            foreach ($belegt as $id) {
                $this->assertSame('Host host_'.$id, DB::table('permissions_groups')->where('id', $id)->value('group_display'));
            }
        }
    }

    // AK1
    public function test_ak1_rechte_freie_wunsch_ids_gehen_an_das_paket_recht_fuer_jede_belegung(): void
    {
        foreach ($this->teilmengen([1, 2, 3, 4, 5, 6, 7, 8]) as $belegt) {
            $this->leere('permissions');
            $this->leere('permissions_groups');
            foreach ($belegt as $id) {
                $this->hostRecht($id, "host_{$id}");
            }

            $this->seed(PermissionsTableSeeder::class);

            $this->pruefeAk1('permissions', 'permission', self::RECHTE, $belegt, 'Rechte belegt='.implode(',', $belegt));
            foreach ($belegt as $id) {
                $this->assertSame('Host host_'.$id, DB::table('permissions')->where('id', $id)->value('permission_display'));
            }
        }
    }

    // AK1
    public function test_ak1_host_rolle_auf_wunsch_id_von_admin_laesst_dev_und_user_auf_ihren_ids(): void
    {
        $this->hostRolle(2, 'chef');

        $this->seed(RolesTableSeeder::class);

        $this->assertSame(1, $this->id('roles', 'role', 'dev'));
        $this->assertSame(3, $this->id('roles', 'role', 'user'));
        $this->assertNotContains($this->id('roles', 'role', 'admin'), [1, 2, 3]);
        $this->assertSame(2, $this->id('roles', 'role', 'chef'));
    }

    // AK1
    public function test_ak1_zweiter_lauf_aendert_nach_ausweichen_nichts(): void
    {
        $this->hostRolle(2, 'chef');
        $this->hostGruppe(1, 'kunden');
        $this->hostRecht(3, 'kunden_read');
        $this->seedAlle();
        $erster = $this->schnappschuss();
        $this->assertCount(4, $erster['roles']);

        $this->seedAlle();

        $this->assertSame($erster, $this->schnappschuss());
    }

    // AK1
    public function test_ak1_databaseseeder_vergibt_freie_wunsch_ids_zuerst(): void
    {
        $this->hostRolle(2, 'chef');
        $this->assertSame(1, DB::table('roles')->count());

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, $this->id('roles', 'role', 'dev'));
        $this->assertSame(3, $this->id('roles', 'role', 'user'));
        $this->assertNotContains($this->id('roles', 'role', 'admin'), [1, 2, 3]);
        $this->assertSame(2, $this->id('roles', 'role', 'chef'));
    }

    // AK2
    public function test_ak2_chef_auf_id_1_admin_bekommt_2_und_user_3(): void
    {
        $this->hostRolle(1, 'chef');

        $this->seed(RolesTableSeeder::class);

        $this->assertSame(2, $this->id('roles', 'role', 'admin'));
        $this->assertSame(3, $this->id('roles', 'role', 'user'));
    }

    // AK2
    public function test_ak2_chef_auf_id_1_dev_bekommt_eine_freie_id_ungleich_1_bis_3(): void
    {
        $this->hostRolle(1, 'chef');

        $this->seed(RolesTableSeeder::class);

        $this->assertNotContains($this->id('roles', 'role', 'dev'), [1, 2, 3]);
        $this->assertSame(4, DB::table('roles')->count());
    }

    // AK2
    public function test_ak2_chef_bleibt_unveraendert(): void
    {
        $this->hostRolle(1, 'chef');
        $vorher = $this->zeile('roles', 'role', 'chef');
        $this->assertSame(1, (int) $vorher['id']);

        $this->seed(RolesTableSeeder::class);

        $this->assertNotNull($this->zeile('roles', 'role', 'admin'), 'Seeder hat nichts angelegt');
        $this->assertSame($vorher, $this->zeile('roles', 'role', 'chef'));
    }

    // AK3
    public function test_ak3_gruppe_kunden_auf_id_1_role_behaelt_id_2(): void
    {
        $this->hostGruppe(1, 'kunden');

        $this->seed(PermissionsGroupsTableSeeder::class);

        $this->assertSame(2, $this->id('permissions_groups', 'group_name', 'role'));
    }

    // AK3
    public function test_ak3_gruppe_kunden_auf_id_1_user_bekommt_freie_id_ungleich_1_und_2(): void
    {
        $this->hostGruppe(1, 'kunden');

        $this->seed(PermissionsGroupsTableSeeder::class);

        $this->assertNotContains($this->id('permissions_groups', 'group_name', 'user'), [1, 2]);
        $this->assertSame(3, DB::table('permissions_groups')->count());
    }

    // AK3
    public function test_ak3_gruppe_kunden_bleibt_unveraendert(): void
    {
        $this->hostGruppe(1, 'kunden');
        $vorher = $this->zeile('permissions_groups', 'group_name', 'kunden');
        $this->assertSame(1, (int) $vorher['id']);

        $this->seed(PermissionsGroupsTableSeeder::class);

        $this->assertNotNull($this->zeile('permissions_groups', 'group_name', 'role'), 'Seeder hat nichts angelegt');
        $this->assertSame($vorher, $this->zeile('permissions_groups', 'group_name', 'kunden'));
    }

    // AK3
    public function test_ak3_host_recht_auf_id_1_uebrige_paket_rechte_behalten_ihre_ids_2_bis_8(): void
    {
        $this->hostRecht(1, 'kunden_read');

        $this->seedAlle();

        foreach (self::RECHTE as $name => $wunsch) {
            if ($wunsch >= 2) {
                $this->assertSame($wunsch, $this->id('permissions', 'permission', $name), $name);
            }
        }
        $this->assertNotContains($this->id('permissions', 'permission', 'user_create'), range(1, 8));
    }

    // AK3
    public function test_ak3_host_recht_auf_id_1_bleibt_unveraendert(): void
    {
        $this->hostRecht(1, 'kunden_read');
        $vorher = $this->zeile('permissions', 'permission', 'kunden_read');
        $this->assertSame(1, (int) $vorher['id']);

        $this->seedAlle();

        $this->assertNotNull($this->zeile('permissions', 'permission', 'role_read'), 'Seeder hat nichts angelegt');
        $this->assertSame($vorher, $this->zeile('permissions', 'permission', 'kunden_read'));
    }

    // Randfall 1
    public function test_randfall1_leere_tabellen_alle_eintraege_auf_ihren_wunsch_ids(): void
    {
        $this->assertSame(0, DB::table('roles')->count() + DB::table('permissions_groups')->count() + DB::table('permissions')->count());

        $this->seedAlle();

        foreach (self::ROLLEN as $name => $id) {
            $this->assertSame($id, $this->id('roles', 'role', $name), $name);
        }
        foreach (self::GRUPPEN as $name => $id) {
            $this->assertSame($id, $this->id('permissions_groups', 'group_name', $name), $name);
        }
        foreach (self::RECHTE as $name => $id) {
            $this->assertSame($id, $this->id('permissions', 'permission', $name), $name);
        }
    }

    // Randfall 2
    public function test_randfall2_alle_wunsch_ids_belegt_rollen_weichen_aus_und_ueberschreiben_keinen_host_eintrag(): void
    {
        $this->hostRolle(1, 'h1');
        $this->hostRolle(2, 'h2');
        $this->hostRolle(3, 'h3');
        $vorher = DB::table('roles')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $this->assertCount(3, $vorher);

        $this->seed(RolesTableSeeder::class);

        $this->assertSame(6, DB::table('roles')->count());
        $ids = [];
        foreach (array_keys(self::ROLLEN) as $name) {
            $ids[] = $this->id('roles', 'role', $name);
        }
        $this->assertCount(3, array_unique($ids));
        $this->assertSame([], array_intersect($ids, [1, 2, 3]));
        $this->assertSame($vorher, DB::table('roles')->whereIn('id', [1, 2, 3])->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
    }

    // Randfall 2
    public function test_randfall2_alle_wunsch_ids_belegt_gruppen_weichen_aus_und_ueberschreiben_keinen_host_eintrag(): void
    {
        $this->hostGruppe(1, 'h1');
        $this->hostGruppe(2, 'h2');
        $vorher = DB::table('permissions_groups')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $this->assertCount(2, $vorher);

        $this->seed(PermissionsGroupsTableSeeder::class);

        $this->assertSame(4, DB::table('permissions_groups')->count());
        $ids = [$this->id('permissions_groups', 'group_name', 'user'), $this->id('permissions_groups', 'group_name', 'role')];
        $this->assertCount(2, array_unique($ids));
        $this->assertSame([], array_intersect($ids, [1, 2]));
        $this->assertSame($vorher, DB::table('permissions_groups')->whereIn('id', [1, 2])->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
    }

    // Randfall 2
    public function test_randfall2_alle_wunsch_ids_belegt_rechte_weichen_aus_und_ueberschreiben_keinen_host_eintrag(): void
    {
        foreach (range(1, 8) as $id) {
            $this->hostRecht($id, "h{$id}");
        }
        $vorher = DB::table('permissions')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $this->assertCount(8, $vorher);

        $this->seed(PermissionsTableSeeder::class);

        $this->assertSame(16, DB::table('permissions')->count());
        $ids = [];
        foreach (array_keys(self::RECHTE) as $name) {
            $ids[] = $this->id('permissions', 'permission', $name);
        }
        $this->assertCount(8, array_unique($ids));
        $this->assertSame([], array_intersect($ids, range(1, 8)));
        $this->assertSame($vorher, DB::table('permissions')->whereIn('id', range(1, 8))->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
    }

    // Randfall 3
    public function test_randfall3_rechte_folgen_der_ausgewichenen_gruppe_ueber_den_gruppennamen(): void
    {
        $this->hostGruppe(1, 'kunden');
        $this->hostGruppe(2, 'projekt');

        $this->seedAlle();

        $userGruppe = $this->id('permissions_groups', 'group_name', 'user');
        $roleGruppe = $this->id('permissions_groups', 'group_name', 'role');
        $this->assertNotContains($userGruppe, [1, 2], 'Gruppe user ist nicht ausgewichen');
        $this->assertNotContains($roleGruppe, [1, 2], 'Gruppe role ist nicht ausgewichen');
        foreach (['user_create', 'user_read', 'user_edit', 'user_delete'] as $recht) {
            $this->assertSame($userGruppe, (int) $this->zeile('permissions', 'permission', $recht)['group_id'], $recht);
        }
        foreach (['role_create', 'role_read', 'role_edit', 'role_delete'] as $recht) {
            $this->assertSame($roleGruppe, (int) $this->zeile('permissions', 'permission', $recht)['group_id'], $recht);
        }
    }

    // Randfall 3
    public function test_randfall3_rechte_folgen_nur_der_ausgewichenen_gruppe_user_waehrend_role_auf_id_2_bleibt(): void
    {
        $this->hostGruppe(1, 'kunden');

        $this->seedAlle();

        $userGruppe = $this->id('permissions_groups', 'group_name', 'user');
        $this->assertNotContains($userGruppe, [1, 2]);
        $this->assertSame(2, $this->id('permissions_groups', 'group_name', 'role'));
        foreach (['user_create', 'user_delete'] as $recht) {
            $this->assertSame($userGruppe, (int) $this->zeile('permissions', 'permission', $recht)['group_id'], $recht);
        }
        foreach (['role_create', 'role_delete'] as $recht) {
            $this->assertSame(2, (int) $this->zeile('permissions', 'permission', $recht)['group_id'], $recht);
        }
    }
}
