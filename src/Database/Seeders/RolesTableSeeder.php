<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Database\Seeders;

use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    use LegtNachNamenAn;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $rollen = [
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer'],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin'],
            ['id' => 3, 'role' => 'user', 'role_display' => 'Anwender'],
        ];

        $this->legeFehlendeAn('roles', 'role', $rollen);
    }
}
