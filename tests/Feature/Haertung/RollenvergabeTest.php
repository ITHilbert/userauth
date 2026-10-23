<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Haertung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Rollenvergabe an Nutzer und Rechtevergabe an Rollen. Abgedeckt: AK9, AK10 (userauth-haertung, Entscheidung 0006).
 */
final class RollenvergabeTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    private const ROLLE_EDITOR = 4;

    private const ROLLE_GELOESCHT = 9;

    private const BESTAETIGUNG = 'Geheim-Passwort-123!';

    protected function setUp(): void
    {
        parent::setUp();

        $jetzt = now();
        $rolle = fn (int $id, string $name, ?object $geloescht = null): array => [
            'id' => $id, 'role' => $name, 'role_display' => ucfirst($name),
            'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => $geloescht,
        ];
        DB::table('roles')->insert([
            $rolle(1, 'dev'),
            $rolle(2, 'admin'),
            $rolle(self::ROLLE_NORMAL, 'user'),
            $rolle(self::ROLLE_EDITOR, 'editor'),
            $rolle(self::ROLLE_GELOESCHT, 'altrolle', $jetzt),
        ]);

        // 1-5: Rechte des handelnden Nutzers; 6-7: Rechte, die vergeben werden
        foreach (['user_create', 'user_edit', 'role_read', 'role_create', 'role_edit', 'vergabe_x', 'vergabe_y'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
        foreach ([1, 2, 3, 4, 5] as $permissionId) {
            DB::table('role_permission')->insert(['role_id' => self::ROLLE_NORMAL, 'permission_id' => $permissionId]);
        }
        // Rolle "editor" besitzt zu Beginn genau das Recht vergabe_x (6)
        DB::table('role_permission')->insert(['role_id' => self::ROLLE_EDITOR, 'permission_id' => 6]);
    }

    /**
     * @return array<string, array{0: int|string}>
     */
    public static function ungueltigeRollenIds(): array
    {
        return [
            'nicht existierende Rolle' => [999],
            'gelöschte Rolle' => [self::ROLLE_GELOESCHT],
            'Null' => [0],
            'negativ' => [-1],
            'kein Integer' => ['abc'],
        ];
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function gueltigeRollenIds(): array
    {
        return [
            'Dev (1)' => [1],
            'Admin (2)' => [2],
            'normale Rolle (3)' => [3],
            'weitere Rolle (4)' => [4],
        ];
    }

    /**
     * @return array<string, array{0: int|string}>
     */
    public static function ungueltigeRechteIds(): array
    {
        return [
            'nicht existierende Rechte-ID' => [999],
            'Null' => [0],
            'negativ' => [-1],
            'kein Integer' => ['abc'],
        ];
    }

    private function handelnderNutzer(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_NORMAL]);
    }

    private function zielNutzer(): User
    {
        return User::factory()->create([
            'email' => 'ziel@example.test',
            'name' => 'Ziel Nutzer',
            'role_id' => self::ROLLE_NORMAL,
            'password' => bcrypt(self::BESTAETIGUNG),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function neuerNutzerDaten(int|string $roleId): array
    {
        return [
            'name' => 'Neuer Nutzer',
            'email' => 'neu@example.test',
            'password' => 'Ein-Passwort-456!',
            'password2' => 'Ein-Passwort-456!',
            'role_id' => $roleId,
        ];
    }

    /**
     * @return list<int>
     */
    private function rechteDerRolle(int $roleId): array
    {
        return DB::table('role_permission')->where('role_id', $roleId)->orderBy('permission_id')->pluck('permission_id')->map(fn ($id): int => (int) $id)->all();
    }

    // ---------------------------------------------------------------- AK9 Anlegen

    // AK9
    #[DataProvider('ungueltigeRollenIds')]
    public function test_anlegen_mit_ungueltiger_rolle_wird_abgelehnt(int|string $roleId): void
    {
        $this->actingAs($this->handelnderNutzer())
            ->post('/users/store', $this->neuerNutzerDaten($roleId))
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'neu@example.test']);
    }

    // AK9
    #[DataProvider('gueltigeRollenIds')]
    public function test_anlegen_mit_existierender_rolle_wird_angenommen(int $roleId): void
    {
        $this->actingAs($this->handelnderNutzer())
            ->post('/users/store', $this->neuerNutzerDaten($roleId))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user'));

        $this->assertDatabaseHas('users', ['email' => 'neu@example.test', 'role_id' => $roleId]);
    }

    // ---------------------------------------------------------------- AK9 Bearbeiten

    // AK9
    #[DataProvider('ungueltigeRollenIds')]
    public function test_bearbeiten_mit_ungueltiger_rolle_wird_abgelehnt(int|string $roleId): void
    {
        $ziel = $this->zielNutzer();

        $this->actingAs($this->handelnderNutzer())
            ->post("/users/update/{$ziel->id}", ['name' => 'Anderer Name', 'email' => 'ziel@example.test', 'role_id' => $roleId])
            ->assertSessionHasErrors('role_id');

        $ziel->refresh();
        $this->assertSame(self::ROLLE_NORMAL, (int) $ziel->role_id);
        $this->assertSame('Ziel Nutzer', $ziel->name);
    }

    // AK9
    #[DataProvider('ungueltigeRollenIds')]
    public function test_bearbeiten_mit_neuem_passwort_und_ungueltiger_rolle_wird_abgelehnt(int|string $roleId): void
    {
        $ziel = $this->zielNutzer();
        $hashVorher = $ziel->password;

        $this->actingAs($this->handelnderNutzer())
            ->post("/users/update/{$ziel->id}", [
                'name' => 'Ziel Nutzer', 'email' => 'ziel@example.test', 'role_id' => $roleId,
                'password' => 'Neues-Passwort-789!', 'password2' => 'Neues-Passwort-789!',
            ])
            ->assertSessionHasErrors('role_id');

        $ziel->refresh();
        $this->assertSame(self::ROLLE_NORMAL, (int) $ziel->role_id);
        $this->assertSame($hashVorher, $ziel->password);
    }

    // AK9
    #[DataProvider('ungueltigeRollenIds')]
    public function test_bearbeiten_mit_geaenderter_email_und_ungueltiger_rolle_wird_abgelehnt(int|string $roleId): void
    {
        $ziel = $this->zielNutzer();

        $this->actingAs($this->handelnderNutzer())
            ->post("/users/update/{$ziel->id}", ['name' => 'Ziel Nutzer', 'email' => 'andere@example.test', 'role_id' => $roleId])
            ->assertSessionHasErrors('role_id');

        $ziel->refresh();
        $this->assertSame('ziel@example.test', $ziel->email);
        $this->assertSame(self::ROLLE_NORMAL, (int) $ziel->role_id);
    }

    // AK9
    #[DataProvider('gueltigeRollenIds')]
    public function test_bearbeiten_mit_existierender_rolle_wird_angenommen(int $roleId): void
    {
        $ziel = $this->zielNutzer();

        $this->actingAs($this->handelnderNutzer())
            ->post("/users/update/{$ziel->id}", ['name' => 'Ziel Nutzer', 'email' => 'ziel@example.test', 'role_id' => $roleId])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user'));

        $this->assertSame($roleId, (int) $ziel->refresh()->role_id);
    }

    // ---------------------------------------------------------------- AK10 Anlegen

    // AK10
    #[DataProvider('ungueltigeRechteIds')]
    public function test_rolle_anlegen_mit_ungueltiger_rechte_id_wird_abgelehnt(int|string $rechteId): void
    {
        $this->actingAs($this->handelnderNutzer())
            ->post('/roles/store', ['role_display' => 'Neue Rolle', 'role' => 'neue_rolle', 'permission' => [$rechteId]])
            ->assertSessionMissing('message');

        $this->assertDatabaseMissing('roles', ['role' => 'neue_rolle']);
    }

    // AK10
    #[DataProvider('ungueltigeRechteIds')]
    public function test_rolle_anlegen_mit_gemischter_rechte_liste_speichert_nichts(int|string $rechteId): void
    {
        $verknuepfungenVorher = DB::table('role_permission')->count();

        $this->actingAs($this->handelnderNutzer())
            ->post('/roles/store', ['role_display' => 'Neue Rolle', 'role' => 'neue_rolle', 'permission' => [6, $rechteId, 7]])
            ->assertSessionMissing('message');

        $this->assertDatabaseMissing('roles', ['role' => 'neue_rolle']);
        $this->assertSame($verknuepfungenVorher, DB::table('role_permission')->count());
    }

    // AK10 (Regressionsschutz: gültige Rechte werden vollständig gespeichert)
    public function test_rolle_anlegen_mit_gueltigen_rechten_speichert_alle(): void
    {
        $this->actingAs($this->handelnderNutzer())
            ->post('/roles/store', ['role_display' => 'Neue Rolle', 'role' => 'neue_rolle', 'permission' => [6, 7]])
            ->assertRedirect(route('role'));

        $neueRolleId = (int) DB::table('roles')->where('role', 'neue_rolle')->value('id');
        $this->assertGreaterThan(0, $neueRolleId);
        $this->assertSame([6, 7], $this->rechteDerRolle($neueRolleId));
    }

    // ---------------------------------------------------------------- AK10 Bearbeiten

    // AK10
    #[DataProvider('ungueltigeRechteIds')]
    public function test_rolle_bearbeiten_mit_ungueltiger_rechte_id_laesst_rechte_unveraendert(int|string $rechteId): void
    {
        $this->actingAs($this->handelnderNutzer())
            ->post('/roles/update/'.self::ROLLE_EDITOR, ['role_display' => 'Editor Neu', 'role' => 'editor_neu', 'permission' => [$rechteId]])
            ->assertSessionMissing('message');

        $this->assertSame([6], $this->rechteDerRolle(self::ROLLE_EDITOR));
        $this->assertDatabaseHas('roles', ['id' => self::ROLLE_EDITOR, 'role' => 'editor']);
    }

    // AK10
    #[DataProvider('ungueltigeRechteIds')]
    public function test_rolle_bearbeiten_mit_gemischter_rechte_liste_aendert_nichts(int|string $rechteId): void
    {
        $this->actingAs($this->handelnderNutzer())
            ->post('/roles/update/'.self::ROLLE_EDITOR, ['role_display' => 'Editor Neu', 'role' => 'editor_neu', 'permission' => [7, $rechteId]])
            ->assertSessionMissing('message');

        $this->assertSame([6], $this->rechteDerRolle(self::ROLLE_EDITOR));
        $this->assertDatabaseHas('roles', ['id' => self::ROLLE_EDITOR, 'role' => 'editor']);
    }

    // AK10 (Regressionsschutz: gültige Rechte ersetzen die bisherigen vollständig)
    public function test_rolle_bearbeiten_mit_gueltigen_rechten_ersetzt_die_rechte(): void
    {
        $this->actingAs($this->handelnderNutzer())
            ->post('/roles/update/'.self::ROLLE_EDITOR, ['role_display' => 'Editor Neu', 'role' => 'editor_neu', 'permission' => [7]])
            ->assertRedirect(route('role'));

        $this->assertSame([7], $this->rechteDerRolle(self::ROLLE_EDITOR));
    }
}
