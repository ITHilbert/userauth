<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::table('roles')->delete();

        DB::table('roles')->insert([
            0 => [
                'id' => 1,
                'role' => 'dev',
                'role_display' => 'Developer',
            ],
            1 => [
                'id' => 2,
                'role' => 'admin',
                'role_display' => 'Admin',
            ],
            2 => [
                'id' => 3,
                'role' => 'user',
                'role_display' => 'Anwender',
            ],
        ]);
    }
}
