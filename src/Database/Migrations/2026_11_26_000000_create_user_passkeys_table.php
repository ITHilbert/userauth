<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            // Kennung der Anmeldeinformation, base64url (ohne Auffüllzeichen)
            $table->string('credential_id', 255)->unique();
            // Anmeldeinformation als JSON: öffentlicher Schlüssel, Benutzer-Handle, Signaturzähler
            $table->text('credential_source');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_passkeys');
    }
};
