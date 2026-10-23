<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Haertung;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ITHilbert\UserAuth\Application\Zugriff\Zugriffspruefung;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Paket-Migrationen gegen eine bestehende `users`-Tabelle und die Standardrolle.
 * Abgedeckt: AK23, AK24 (userauth-haertung, Befund Q1).
 *
 * Der Test steuert die Migrationsreihenfolge selbst: kein Vorab-Lauf der Laravel-Standardmigrationen
 * (deren Paket-Migration 2019_10_12 legte `users` bisher per dropIfExists neu an und löschte alle Nutzer).
 */
final class UsersMigrationTest extends TestCase
{
    /** Migrationen, die eine bestehende Installation vor diesem Feature bereits ausgeführt hat. */
    private const ALTE_MIGRATIONEN = [
        '2019_10_12_000000_create_my_users_table',
        '2020_04_23_133628_create_roles_table',
        '2020_04_23_133642_create_permissions_table',
        '2020_04_23_133643_create_permissions_groups_table',
        '2020_04_23_140937_create_role_permission_table',
        '2026_02_17_000000_create_user_auth_audit_logs_table',
        '2026_02_17_100000_add_2fa_columns_to_users_table',
        '2026_03_28_000000_create_password_policy_tables',
        '2026_03_28_000001_create_teams_tables',
        '2026_09_29_000000_create_user_social_accounts_table',
        '2026_09_29_000001_create_user_pending_social_links_table',
    ];

    /** Spalten, die das Paket in `users` voraussetzt. */
    private const PAKET_SPALTEN = [
        'role_id', 'anrede_id', 'title', 'firstname', 'lastname', 'name', 'smallname', 'email', 'email_verified_at',
        'password', 'remember_token', 'edit_pw_token', 'edit_pw_token_end', 'street', 'postcode', 'city', 'country',
        'signature_rule_id', 'ustid', 'phone', 'phone2', 'mobile', 'fax', 'private_email', 'skype', 'hourly_rate',
        'birthday', 'comment', 'image', 'created_at', 'updated_at', 'deleted_at',
        'two_factor_code', 'two_factor_expires_at', 'password_changed_at', 'current_team_id',
    ];

    protected function defineDatabaseMigrations(): void
    {
        // bewusst leer: die Reihenfolge der Migrationen legt jeder Test selbst fest
    }

    // ---------------------------------------------------------------- Hilfen

    private function paketMigrationenAusfuehren(): void
    {
        $exit = Artisan::call('migrate', [
            '--path' => [dirname(__DIR__, 3).'/src/Database/Migrations'],
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
    }

    /**
     * Tabelle eines Hosts, der das Paket noch nicht nutzt (Laravel-Standard: nur Name, E-Mail, Passwort).
     */
    private function fremdeUsersTabelleMitNutzern(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        $jetzt = now();
        foreach ([[11, 'Anna', 'anna@firma.de'], [12, 'Bernd', 'bernd@firma.de'], [13, 'Clara', 'clara@firma.de']] as [$id, $name, $email]) {
            DB::table('users')->insert([
                'id' => $id, 'name' => $name, 'email' => $email, 'password' => "hash-{$id}",
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
    }

    /**
     * Nutzer-Tabelle einer Installation mit dem bisherigen Paket-Schema (role_id NOT NULL, Default 2) und ihre
     * Migrationshistorie.
     */
    private function bestehendePaketInstallation(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->bigInteger('role_id')->default(2);
            $table->string('name', 100)->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Artisan::call('migrate:install');
        foreach (self::ALTE_MIGRATIONEN as $migration) {
            DB::table('migrations')->insert(['migration' => $migration, 'batch' => 1]);
        }

        $jetzt = now();
        foreach ([[1, 1, 'dev@firma.de'], [2, 2, 'admin@firma.de'], [3, 3, 'user@firma.de']] as [$id, $rolle, $email]) {
            DB::table('users')->insert([
                'id' => $id, 'role_id' => $rolle, 'name' => $email, 'email' => $email, 'password' => 'x',
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
    }

    private function rolleOhnePrivileg(mixed $roleId): bool
    {
        return $roleId === null || ! in_array((int) $roleId, [1, 2], true);
    }

    private function rollenAnlegen(): void
    {
        $jetzt = now();
        foreach ([[1, 'dev'], [2, 'admin'], [3, 'user']] as [$id, $schluessel]) {
            DB::table('roles')->insert([
                'id' => $id, 'role' => $schluessel, 'role_display' => ucfirst($schluessel),
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
    }

    // ------------------------------------------------------------------ AK23

    // AK23
    public function test_bestehende_nutzer_bleiben_beim_migrieren_erhalten(): void
    {
        $this->fremdeUsersTabelleMitNutzern();

        $this->paketMigrationenAusfuehren();

        $this->assertSame(3, DB::table('users')->count());
        $this->assertDatabaseHas('users', ['id' => 11, 'name' => 'Anna', 'email' => 'anna@firma.de', 'password' => 'hash-11']);
        $this->assertDatabaseHas('users', ['id' => 12, 'name' => 'Bernd', 'email' => 'bernd@firma.de', 'password' => 'hash-12']);
        $this->assertDatabaseHas('users', ['id' => 13, 'name' => 'Clara', 'email' => 'clara@firma.de', 'password' => 'hash-13']);
    }

    // AK23
    public function test_fehlende_spalten_werden_ergaenzt(): void
    {
        $this->fremdeUsersTabelleMitNutzern();

        $this->paketMigrationenAusfuehren();

        foreach (self::PAKET_SPALTEN as $spalte) {
            $this->assertTrue(Schema::hasColumn('users', $spalte), "Spalte users.{$spalte} fehlt nach der Migration.");
        }
    }

    // AK23
    public function test_bestehende_nutzer_sind_nach_der_migration_mit_dem_paket_model_lesbar(): void
    {
        $this->fremdeUsersTabelleMitNutzern();
        $this->paketMigrationenAusfuehren();

        $this->assertSame(3, User::query()->count());
        $this->assertSame('anna@firma.de', User::query()->findOrFail(11)->email);
    }

    // AK23 (die übrigen Paket-Tabellen entstehen trotzdem)
    public function test_die_uebrigen_paket_tabellen_werden_angelegt(): void
    {
        $this->fremdeUsersTabelleMitNutzern();

        $this->paketMigrationenAusfuehren();

        foreach (['roles', 'permissions', 'role_permission', 'user_auth_audit_logs', 'user_password_histories', 'user_social_accounts'] as $tabelle) {
            $this->assertTrue(Schema::hasTable($tabelle), "Tabelle {$tabelle} fehlt.");
        }
    }

    // AK23 (erneutes Ausführen ändert nichts an den Nutzern)
    public function test_erneutes_migrieren_laesst_die_nutzer_unveraendert(): void
    {
        $this->fremdeUsersTabelleMitNutzern();
        $this->paketMigrationenAusfuehren();

        $this->paketMigrationenAusfuehren();

        $this->assertSame(3, DB::table('users')->count());
    }

    // AK23 (Gegenprobe: auf leerer Datenbank wird `users` mit allen Spalten angelegt)
    public function test_ohne_bestehende_users_tabelle_wird_sie_vollstaendig_angelegt(): void
    {
        $this->paketMigrationenAusfuehren();

        foreach (self::PAKET_SPALTEN as $spalte) {
            $this->assertTrue(Schema::hasColumn('users', $spalte), "Spalte users.{$spalte} fehlt.");
        }
    }

    // ------------------------------------------------------------------ AK24

    // AK24 (neue Installation, Eloquent)
    public function test_ein_nutzer_ohne_role_id_hat_in_einer_neuen_installation_keine_privilegierte_rolle(): void
    {
        $this->paketMigrationenAusfuehren();
        $this->rollenAnlegen();

        $nutzer = User::factory()->create(['email' => 'ohne-rolle@firma.de']);

        $this->assertTrue(
            $this->rolleOhnePrivileg(DB::table('users')->where('id', $nutzer->getKey())->value('role_id')),
            'Die Spalte role_id darf ohne Angabe nicht Dev (1) oder Admin (2) sein.',
        );
    }

    // AK24 (neue Installation, Zugriffsprüfung)
    public function test_ein_nutzer_ohne_role_id_wird_von_der_zugriffspruefung_nicht_privilegiert_behandelt(): void
    {
        $this->paketMigrationenAusfuehren();
        $this->rollenAnlegen();
        $nutzer = User::factory()->create(['email' => 'ohne-rolle@firma.de'])->fresh();

        $pruefung = $this->app->make(Zugriffspruefung::class);

        $this->assertFalse($pruefung->istPrivilegiert($nutzer));
        $this->assertFalse($pruefung->istDev($nutzer));
    }

    // AK24 (neue Installation, direkter Insert)
    public function test_ein_direkt_eingefuegter_nutzer_ohne_role_id_hat_keine_privilegierte_rolle(): void
    {
        $this->paketMigrationenAusfuehren();
        $jetzt = now();

        DB::table('users')->insert(['id' => 50, 'name' => 'X', 'email' => 'x@firma.de', 'password' => 'x', 'created_at' => $jetzt, 'updated_at' => $jetzt]);

        $this->assertTrue($this->rolleOhnePrivileg(DB::table('users')->where('id', 50)->value('role_id')));
    }

    // AK24 (bestehende Installation nach Migration)
    public function test_ein_nutzer_ohne_role_id_hat_in_einer_bestehenden_installation_nach_der_migration_keine_privilegierte_rolle(): void
    {
        $this->bestehendePaketInstallation();
        $this->paketMigrationenAusfuehren();
        $jetzt = now();

        DB::table('users')->insert(['id' => 50, 'name' => 'X', 'email' => 'x@firma.de', 'password' => 'x', 'created_at' => $jetzt, 'updated_at' => $jetzt]);

        $this->assertTrue(
            $this->rolleOhnePrivileg(DB::table('users')->where('id', 50)->value('role_id')),
            'Auch nach dem Update einer bestehenden Installation darf der Spaltenstandard nicht Dev (1) oder Admin (2) sein.',
        );
    }

    // AK23 + AK24 (vorhandene Rollenzuordnungen bleiben beim Update unverändert)
    public function test_vorhandene_rollenzuordnungen_bleiben_beim_update_einer_bestehenden_installation_erhalten(): void
    {
        $this->bestehendePaketInstallation();

        $this->paketMigrationenAusfuehren();

        $this->assertSame(3, DB::table('users')->count());
        $this->assertSame(1, (int) DB::table('users')->where('id', 1)->value('role_id'));
        $this->assertSame(2, (int) DB::table('users')->where('id', 2)->value('role_id'));
        $this->assertSame(3, (int) DB::table('users')->where('id', 3)->value('role_id'));
    }

    // AK24 (Nutzer einer fremden users-Tabelle ohne role_id werden durch die neue Spalte nicht zu Admins)
    public function test_bestehende_nutzer_ohne_rolle_erhalten_durch_die_neue_spalte_keine_privilegierte_rolle(): void
    {
        $this->fremdeUsersTabelleMitNutzern();

        $this->paketMigrationenAusfuehren();

        $rollen = DB::table('users')->pluck('role_id', 'id');
        $this->assertCount(3, $rollen, 'Die bestehenden Nutzer müssen erhalten bleiben.');
        foreach ($rollen as $id => $roleId) {
            $this->assertTrue($this->rolleOhnePrivileg($roleId), "Nutzer {$id} hat nach der Migration die privilegierte role_id {$roleId}.");
        }
    }

    // AK24 (Zugriffsprüfung für bestehende Nutzer einer fremden users-Tabelle)
    public function test_bestehende_nutzer_ohne_rolle_werden_von_der_zugriffspruefung_nicht_privilegiert_behandelt(): void
    {
        $this->fremdeUsersTabelleMitNutzern();
        $this->paketMigrationenAusfuehren();
        $this->rollenAnlegen();

        $pruefung = $this->app->make(Zugriffspruefung::class);

        foreach ([11, 12, 13] as $id) {
            $this->assertFalse($pruefung->istPrivilegiert(User::query()->findOrFail($id)), "Nutzer {$id} gilt als privilegiert.");
        }
    }
}
