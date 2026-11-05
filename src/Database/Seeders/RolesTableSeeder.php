<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Database\Seeders;

use Illuminate\Database\Seeder;
use ITHilbert\UserAuth\Domain\Zugriff\Rolle;

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

        // Keine Rolle `dev` mehr: privilegiert ist nur `admin` (Entscheidung 0011)
        $rollen = [
            ['id' => 2, 'role' => Rolle::NAME_ADMIN, 'role_display' => 'Admin'],
            ['id' => 3, 'role' => 'user', 'role_display' => 'Anwender'],
        ];

        $this->legeFehlendeAn('roles', 'role', $rollen);
    }
}
