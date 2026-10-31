<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsTableSeeder extends Seeder
{
    use LegtNachNamenAn;

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        $this->call(PermissionsGroupsTableSeeder::class);

        $rechte = [
            0 => [
                'id' => 1,
                'permission' => 'user_create',
                'permission_display' => 'Benutzer erstellen',
                'group_name' => 'user',
                'crud' => 'create',
                'created_at' => '2020-08-10 13:25:31',
                'updated_at' => '2020-08-12 12:30:58',
                'deleted_at' => null,
            ],
            1 => [
                'id' => 2,
                'permission' => 'user_read',
                'permission_display' => 'Benutzer lesen',
                'group_name' => 'user',
                'crud' => 'read',
                'created_at' => '2020-08-10 13:25:31',
                'updated_at' => '2020-08-12 12:30:58',
                'deleted_at' => null,
            ],
            2 => [
                'id' => 3,
                'permission' => 'user_edit',
                'permission_display' => 'Benutzer ändern',
                'group_name' => 'user',
                'crud' => 'edit',
                'created_at' => '2020-08-10 13:25:31',
                'updated_at' => '2020-08-12 12:30:58',
                'deleted_at' => null,
            ],
            3 => [
                'id' => 4,
                'permission' => 'user_delete',
                'permission_display' => 'Benutzer delete',
                'group_name' => 'user',
                'crud' => 'delete',
                'created_at' => '2020-08-10 13:25:31',
                'updated_at' => '2020-08-12 12:30:58',
                'deleted_at' => null,
            ],
            4 => [
                'id' => 5,
                'permission' => 'role_create',
                'permission_display' => 'Rollen erstellen',
                'group_name' => 'role',
                'crud' => 'create',
                'created_at' => '2020-08-12 12:31:35',
                'updated_at' => '2020-08-12 12:31:35',
                'deleted_at' => null,
            ],
            5 => [
                'id' => 6,
                'permission' => 'role_read',
                'permission_display' => 'Rollen lesen',
                'group_name' => 'role',
                'crud' => 'read',
                'created_at' => '2020-08-12 12:31:35',
                'updated_at' => '2020-08-12 12:31:35',
                'deleted_at' => null,
            ],
            6 => [
                'id' => 7,
                'permission' => 'role_edit',
                'permission_display' => 'Rollen ändern',
                'group_name' => 'role',
                'crud' => 'edit',
                'created_at' => '2020-08-12 12:31:35',
                'updated_at' => '2020-08-12 12:31:35',
                'deleted_at' => null,
            ],
            7 => [
                'id' => 8,
                'permission' => 'role_delete',
                'permission_display' => 'Rollen delete',
                'group_name' => 'role',
                'crud' => 'delete',
                'created_at' => '2020-08-12 12:31:35',
                'updated_at' => '2020-08-12 12:31:35',
                'deleted_at' => null,
            ],
        ];

        $zeilen = [];
        foreach ($rechte as $recht) {
            $recht['group_id'] = DB::table('permissions_groups')->where('group_name', $recht['group_name'])->value('id');
            unset($recht['group_name']);
            $zeilen[] = $recht;
        }

        $this->legeFehlendeAn('permissions', 'permission', $zeilen);
    }
}
