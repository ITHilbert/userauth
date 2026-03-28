<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePasswordPolicyTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Erweiterung der users Tabelle um password_changed_at
        if (!Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                // Kann null sein bei Nutzern, die nie eine Policy hatten
                $table->timestamp('password_changed_at')->nullable()->after('password');
            });
        }

        // Neue Tabelle für historische Passwörter aufbauen
        if (!Schema::hasTable('user_password_histories')) {
            Schema::create('user_password_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('password'); // Der gespeicherte Hash
                $table->timestamps();

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_password_histories');

        if (Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password_changed_at');
            });
        }
    }
}
