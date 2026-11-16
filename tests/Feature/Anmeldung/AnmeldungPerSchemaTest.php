<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Anmeldung;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\AnmeldungPerHilfen;
use ITHilbert\UserAuth\Tests\TestCase;
use Throwable;

/**
 * AK13 (Migration) und Randfall „Modus ohne Spalte benutzername“ (userauth-anmeldung-per, #1311).
 */
final class AnmeldungPerSchemaTest extends TestCase
{
    use AnmeldungPerHilfen;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grundeinstellung();
        // Ausgangslage dieser Tests: users ohne Spalte benutzername (auch wenn die Paket-Migration sie schon angelegt hat)
        $this->benutzernameSpalteEntfernen();
    }

    private function migration(): Migration
    {
        $dateien = glob(dirname(__DIR__, 3).'/src/Database/Migrations/*_add_benutzername_to_users_table.php') ?: [];
        $this->assertCount(1, $dateien, 'Es muss genau eine Paket-Migration *_add_benutzername_to_users_table.php geben.');

        return require $dateien[0];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function spalte(string $name): ?array
    {
        foreach (Schema::getColumns('users') as $spalte) {
            if ($spalte['name'] === $name) {
                return $spalte;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function indizesAuf(string $spalte): array
    {
        return array_values(array_filter(
            Schema::getIndexes('users'),
            static fn (array $index): bool => $index['columns'] === [$spalte],
        ));
    }

    // AK13
    public function test_ak13_migration_legt_spalte_benutzername_nullbar_und_eindeutig_an(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'benutzername'), 'Gegenprobe: Ausgangslage ohne Spalte');

        $this->migration()->up();

        $spalte = $this->spalte('benutzername');
        $this->assertNotNull($spalte, 'Die Migration legt die Spalte benutzername nicht an.');
        $this->assertTrue($spalte['nullable']);
        $this->assertStringContainsStringIgnoringCase('char', (string) $spalte['type'], 'Textspalte (die Länge 255 kennt SQLite nicht)');
        $unique = array_filter($this->indizesAuf('benutzername'), static fn (array $i): bool => $i['unique']);
        $this->assertNotEmpty($unique, 'benutzername ist nicht eindeutig indiziert.');

        // Verhalten: mehrere NULL erlaubt, doppelter Wert abgelehnt
        User::factory()->create(['benutzername' => null]);
        User::factory()->create(['benutzername' => null]);
        User::factory()->create(['benutzername' => 'anna.m']);
        $this->expectException(QueryException::class);
        User::factory()->create(['benutzername' => 'anna.m']);
    }

    // AK13
    public function test_ak13_migration_laesst_den_unique_index_auf_email_unangetastet(): void
    {
        $this->migration()->up();

        $email = array_filter($this->indizesAuf('email'), static fn (array $i): bool => $i['unique']);
        $this->assertNotEmpty($email, 'Der Unique-Index auf users.email muss bleiben.');
        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'doppelt@example.test']);
        User::factory()->create(['email' => 'doppelt@example.test']);
    }

    // AK13
    public function test_ak13_vorhandene_spalte_bleibt_unberuehrt_und_up_wiederholbar_down_entfernt_sie_nicht(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->string('benutzername', 100)->nullable(); // Host-eigene Spalte ohne Unique-Index
        });
        DB::table('users')->insert(['name' => 'A', 'email' => 'a@example.test', 'password' => 'x', 'benutzername' => 'host.wert']);
        DB::table('users')->insert(['name' => 'B', 'email' => 'b@example.test', 'password' => 'x', 'benutzername' => 'host.wert']);
        $migration = $this->migration();

        $migration->up();
        $migration->up();

        $this->assertSame(2, DB::table('users')->where('benutzername', 'host.wert')->count());
        $this->assertEmpty(array_filter($this->indizesAuf('benutzername'), static fn (array $i): bool => $i['unique']), 'Die Migration darf an einer vorhandenen Spalte keinen Index ergänzen.');

        $migration->down();

        $this->assertTrue(Schema::hasColumn('users', 'benutzername'), 'down() entfernt eine fremde Spalte.');
        $this->assertSame(2, DB::table('users')->where('benutzername', 'host.wert')->count());
    }

    // Randfall: Wechsel des Modus ohne Spalte benutzername
    public function test_randfall_modus_benutzername_oder_beides_ohne_spalte_ist_ein_konfigurationsfehler_mit_hinweis_auf_die_migration(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'benutzername'));
        User::factory()->create(['email' => 'anna@example.test']);

        // Gegenprobe: Modus email funktioniert ohne die Spalte (AK2)
        $this->modus('email');
        $this->anmelden('email', 'anna@example.test')->assertRedirect('/');
        $this->assertAuthenticated();

        foreach (['benutzername' => 'anna.m', 'beides' => 'anna@example.test'] as $modus => $kennung) {
            $this->neueSitzung();
            $this->modus($modus);
            $this->withoutExceptionHandling();
            $fehler = null;
            try {
                $this->anmelden(self::kennungsFeld($modus), $kennung);
            } catch (Throwable $e) {
                $fehler = $e;
            }
            $this->assertNotNull($fehler, "Modus {$modus} ohne Spalte benutzername: erwartet einen Konfigurationsfehler, kein stiller Fehlschlag.");
            $this->assertStringContainsStringIgnoringCase('migrat', $fehler->getMessage(), "Modus {$modus}: Hinweis auf die Migration fehlt.");
            $this->assertStringContainsString('benutzername', $fehler->getMessage());
            $this->assertGuest();
        }
    }
}
