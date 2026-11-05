<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stellt alle Nutzer (auch weich gelöschte) und Team-Mitgliedschaften mit der Rolle `dev` auf die
 * Rolle `admin` um – privilegiert ist nur noch `admin` (Entscheidung 0011). Ziel ist die nicht
 * gelöschte Rolle `admin` mit der kleinsten ID; gibt es keine, ändert sich nichts. Die Rolle `dev`
 * selbst bleibt stehen, alle übrigen Spalten bleiben unverändert.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $adminId = DB::table('roles')
            ->where('role', 'admin')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        if ($adminId === null) {
            return;
        }

        // Alle Rollen mit dem Schlüssel `dev`, auch gelöschte: Ihre Nutzer verlieren sonst den Zugang.
        $devIds = DB::table('roles')->where('role', 'dev')->pluck('id')->all();

        if ($devIds === []) {
            return;
        }

        // Bewusst Query Builder statt Model: keine Events, kein `updated_at`, kein Scope, der
        // gelöschte Nutzer ausblendet.
        DB::transaction(function () use ($adminId, $devIds): void {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'role_id')) {
                DB::table('users')->whereIn('role_id', $devIds)->update(['role_id' => $adminId]);
            }

            if (Schema::hasTable('team_user') && Schema::hasColumn('team_user', 'role_id')) {
                DB::table('team_user')->whereIn('role_id', $devIds)->update(['role_id' => $adminId]);
            }
        });
    }

    /**
     * Bewusst ohne Wirkung (Entscheidung 0007): Welche Nutzer vorher `dev` hatten, ist nicht mehr
     * bekannt; wer zurück muss, setzt die Rolle per Hand.
     */
    public function down(): void
    {
        //
    }
};
