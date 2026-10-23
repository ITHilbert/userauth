<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nutzer-Tabelle des Pakets.
 *
 * Gibt es `users` noch nicht, wird sie vollständig angelegt. Bringt der Host sie schon mit
 * (z. B. über die Laravel-Standardmigration), bleiben Tabelle und Nutzer erhalten – es werden
 * nur die Spalten ergänzt, die dem Paket fehlen.
 */
class CreateMyUsersTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->bigIncrements('id');

                foreach ($this->spalten() as $spalteAnlegen) {
                    $spalteAnlegen($table);
                }
            });

            return;
        }

        $fehlendeSpalten = array_filter(
            $this->spalten(),
            static fn (string $spalte): bool => ! Schema::hasColumn('users', $spalte),
            ARRAY_FILTER_USE_KEY,
        );

        if ($fehlendeSpalten === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($fehlendeSpalten): void {
            foreach ($fehlendeSpalten as $spalteAnlegen) {
                $spalteAnlegen($table);
            }
        });
    }

    /**
     * Bewusst ohne Wirkung: Die Tabelle kann dem Host gehören (dann hat diese Migration nur Spalten
     * ergänzt), und ob sie vom Paket angelegt wurde, ist beim Zurückrollen nicht mehr feststellbar.
     * Pauschales Löschen würde alle Nutzer des Hosts vernichten. Ein erneutes `up()` ist gefahrlos,
     * weil nur fehlende Spalten ergänzt werden.
     */
    public function down(): void
    {
        //
    }

    /**
     * Spalten, die das Paket in `users` voraussetzt – je Spaltenname die Definition. Dieselbe
     * Definition gilt beim Neuanlegen und beim Ergänzen einer bestehenden Tabelle.
     *
     * @return array<string, Closure(Blueprint): mixed>
     */
    private function spalten(): array
    {
        return [
            // Ohne Standardwert: ein Nutzer ohne Rolle ist nie privilegiert (AK24).
            'role_id' => static fn (Blueprint $table) => $table->bigInteger('role_id')->nullable()->comment('Role'),
            'anrede_id' => static fn (Blueprint $table) => $table->bigInteger('anrede_id')->default(1)->comment('Anrede'),
            'title' => static fn (Blueprint $table) => $table->string('title', 50)->nullable()->comment('Titel'),
            'firstname' => static fn (Blueprint $table) => $table->string('firstname', 50)->nullable()->comment('Vorname'),
            'lastname' => static fn (Blueprint $table) => $table->string('lastname', 50)->nullable()->comment('Nachname'),
            'name' => static fn (Blueprint $table) => $table->string('name', 100)->nullable()->comment('Name'),
            'smallname' => static fn (Blueprint $table) => $table->string('smallname', 10)->nullable()->comment('Kürzel'),
            'email' => static fn (Blueprint $table) => $table->string('email')->unique()->comment('E-Mail'),
            'email_verified_at' => static fn (Blueprint $table) => $table->timestamp('email_verified_at')->nullable()->comment('E-Mail bestätigt am'),
            'password' => static fn (Blueprint $table) => $table->string('password')->comment('Passwort'),
            'remember_token' => static fn (Blueprint $table) => $table->rememberToken(),
            'edit_pw_token' => static fn (Blueprint $table) => $table->string('edit_pw_token', 255)->nullable(),
            'edit_pw_token_end' => static fn (Blueprint $table) => $table->timestamp('edit_pw_token_end')->nullable(),
            'street' => static fn (Blueprint $table) => $table->string('street', 255)->nullable()->comment('Straße'),
            'postcode' => static fn (Blueprint $table) => $table->string('postcode', 255)->nullable()->comment('PLZ'),
            'city' => static fn (Blueprint $table) => $table->string('city', 255)->nullable()->comment('Ort'),
            'country' => static fn (Blueprint $table) => $table->string('country', 255)->nullable()->comment('Land'),
            'signature_rule_id' => static fn (Blueprint $table) => $table->bigInteger('signature_rule_id')->default(1)->comment('Signatur'),
            'ustid' => static fn (Blueprint $table) => $table->string('ustid', 255)->nullable()->comment('UmsatzsteuerID'),
            'phone' => static fn (Blueprint $table) => $table->string('phone', 255)->nullable()->comment('Telefon'),
            'phone2' => static fn (Blueprint $table) => $table->string('phone2', 255)->nullable()->comment('Telefon2'),
            'mobile' => static fn (Blueprint $table) => $table->string('mobile', 255)->nullable()->comment('Mobile'),
            'fax' => static fn (Blueprint $table) => $table->string('fax', 255)->nullable()->comment('Fax'),
            'private_email' => static fn (Blueprint $table) => $table->string('private_email', 255)->nullable()->comment('Private E-Mail'),
            'skype' => static fn (Blueprint $table) => $table->string('skype', 255)->nullable()->comment('Skype'),
            'hourly_rate' => static fn (Blueprint $table) => $table->decimal('hourly_rate', 8, 2)->default(0)->comment('Stundensatz'),
            'birthday' => static fn (Blueprint $table) => $table->date('birthday')->nullable()->comment('Geburtstag'),
            'comment' => static fn (Blueprint $table) => $table->text('comment')->nullable()->comment('Kommentar'),
            'image' => static fn (Blueprint $table) => $table->string('image', 255)->nullable()->comment('Bild'),
            'created_at' => static fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
            'updated_at' => static fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
            'deleted_at' => static fn (Blueprint $table) => $table->softDeletes(),
        ];
    }
}
