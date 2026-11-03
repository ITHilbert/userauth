<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kennzeichen „über die Selbstregistrierung entstanden“. Zusammen mit `email_verified_at` = null ergibt es
 * „Bestätigung offen“ (Login gesperrt). Bestehende Konten bekommen `false` und melden sich an wie bisher.
 * Additiv nach Entscheidung 0007: Spalte nur ergänzen, wenn sie fehlt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'self_registered')) {
            return;
        }

        Schema::table('users', static function (Blueprint $table): void {
            $table->boolean('self_registered')->default(false)->comment('Über die Selbstregistrierung angelegt');
        });
    }

    /**
     * Bewusst ohne Wirkung (Entscheidung 0007): Ein Rückbau würde offene Bestätigungen vergessen und
     * unbestätigte Konten freischalten.
     */
    public function down(): void
    {
        //
    }
};
