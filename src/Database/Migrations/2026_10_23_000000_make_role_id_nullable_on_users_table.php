<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt den privilegierten Standardwert von `users.role_id` (bisher NOT NULL, Default 2 = Admin).
 * Ein Nutzer, der ohne Rolle angelegt wird, bekommt damit keine Rolle und ist nie privilegiert (AK24).
 * Vorhandene Rollenzuordnungen bleiben unverändert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->bigInteger('role_id')->nullable()->comment('Role')->change();
        });
    }

    /**
     * Bewusst ohne Wirkung: Der alte Zustand (NOT NULL, Default Admin) wäre die geschlossene
     * Sicherheitslücke und scheitert zudem, sobald Nutzer ohne Rolle existieren.
     */
    public function down(): void
    {
        //
    }
};
