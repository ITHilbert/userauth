<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTeamsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('team_user')) {
            Schema::create('team_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('role_id')->nullable(); // Rolle innerhalb des Teams
                $table->timestamps();

                $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                // Falls Rollen eine Tabelle haben (haben sie laut Entities/Role)
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
                
                $table->unique(['team_id', 'user_id']);
            });
        }

        // Aktives Team beim User setzen
        if (!Schema::hasColumn('users', 'current_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('current_team_id')->nullable()->after('role_id');
                $table->foreign('current_team_id')->references('id')->on('teams')->onDelete('set null');
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
        if (Schema::hasColumn('users', 'current_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['current_team_id']);
                $table->dropColumn('current_team_id');
            });
        }

        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }
}
