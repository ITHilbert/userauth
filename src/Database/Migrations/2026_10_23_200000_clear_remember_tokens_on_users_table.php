<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leert einmalig `users.remember_token` aller Nutzer – auch soft-gelöschter (AK14a).
 * Alle übrigen Spalten bleiben unverändert.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'remember_token')) {
            return;
        }

        // Angemeldet-bleiben-Cookies aus dem alten Ablauf wurden vor der Zwei-Faktor-Prüfung gesetzt
        // und würden sie umgehen (Entscheidung 0009). Bewusst Query Builder statt Model:
        // keine Events, kein `updated_at`, kein Scope, der gelöschte Nutzer ausblendet.
        DB::table('users')
            ->whereNotNull('remember_token')
            ->update(['remember_token' => null]);
    }

    /**
     * Bewusst ohne Wirkung (Entscheidungen 0007 und 0009): Die geleerten Tokens lassen sich nicht
     * wiederherstellen, und ihr Rückbau würde die geschlossene Lücke wieder öffnen.
     */
    public function down(): void
    {
        //
    }
};
