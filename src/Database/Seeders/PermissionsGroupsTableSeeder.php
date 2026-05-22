<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionsGroupsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        \DB::table('permissions_groups')->delete();

        \DB::table('permissions_groups')->insert([
            0 => [
                'id' => 1,
                'group_name' => 'user',
                'group_display' => 'Benutzer',
                'created_at' => '2020-08-10 13:25:31',
                'updated_at' => '2020-08-12 12:30:58',
                'deleted_at' => null,
            ],
            1 => [
                'id' => 2,
                'group_name' => 'role',
                'group_display' => 'Rollen',
                'created_at' => '2020-08-12 12:31:35',
                'updated_at' => '2020-08-12 12:31:35',
                'deleted_at' => null,
            ],
        ]);

    }
}
