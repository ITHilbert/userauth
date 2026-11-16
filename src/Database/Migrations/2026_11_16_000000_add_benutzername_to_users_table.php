<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spalte `benutzername` für die Anmeldung per Benutzername (`userauth.anmeldung_per`, #1311): nullbar und eindeutig.
 * Additiv nach Entscheidung 0007: nur ergänzen, wenn sie fehlt – eine Spalte des Hosts bleibt unberührt.
 * Der Unique-Index auf `users.email` bleibt; gemeinsame E-Mails brauchen eine Projekt-Migration (README).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'benutzername')) {
            return;
        }

        Schema::table('users', static function (Blueprint $table): void {
            $table->string('benutzername', 255)->nullable()->unique()->comment('Kennung für die Anmeldung per Benutzername');
        });
    }

    /**
     * Bewusst ohne Wirkung (Entscheidung 0007): Ob die Spalte von dieser Migration oder vom Host stammt, ist
     * nicht erkennbar; ein Rückbau würde Benutzernamen löschen und Konten von der Anmeldung aussperren.
     */
    public function down(): void
    {
        //
    }
};
