<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::table('users')->delete();

        DB::table('users')->insert([
            0 => [
                'id' => 1,
                'role_id' => 1,
                'name' => 'admin',
                'email' => 'admin@admin.com',
                'email_verified_at' => null,
                'password' => '$2y$10$EhxU5PZ6BfYLXzjZz83hXOq3hiYGkzuTNge5CjPXMbgN8k63C/U9u',
                'created_at' => '2020-02-14 14:01:04',
                'updated_at' => '2020-02-14 14:01:04',
                'deleted_at' => null,
            ],
        ]);
    }
}
