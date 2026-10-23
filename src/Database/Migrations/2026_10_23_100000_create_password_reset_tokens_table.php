<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelle des Laravel-Password-Brokers (gehashte Reset-Tokens) für Hosts, deren eigene
 * Migrationen sie nicht anlegen. Name aus `auth.passwords.<broker>.table`; vorhandene Tabelle bleibt unberührt.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tabelle = $this->tabelle();

        if (Schema::hasTable($tabelle)) {
            return;
        }

        Schema::create($tabelle, function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Bewusst ohne Wirkung (wie Entscheidung 0007): Ob das Paket die Tabelle angelegt hat oder der Host,
     * ist beim Zurückrollen nicht feststellbar; ein Löschen würde den Passwort-Reset des Hosts zerstören.
     */
    public function down(): void
    {
        //
    }

    private function tabelle(): string
    {
        $broker = (string) config('auth.defaults.passwords', 'users');

        return (string) config("auth.passwords.{$broker}.table", 'password_reset_tokens');
    }
};
