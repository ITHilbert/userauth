<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Haertung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Zugriffsprüfung (Middleware, Gate, Blade) bei ungültiger/privilegierter Rolle und
 * bei den Verknüpfungen And/Or. Abgedeckt: AK11, AK12, AK13 (userauth-haertung).
 */
final class ZugriffspruefungTest extends TestCase
{
    use RefreshDatabase;

    private const GEHEIM = 'GEHEIMER-INHALT';

    private const ROLLE_GELOESCHT = 7;

    private const ROLLE_NORMAL = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => null],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => null],
            ['id' => self::ROLLE_NORMAL, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => null],
            // gelöschte Rolle: trägt Namen "admin" und alle Rechte, darf aber nichts mehr vererben
            ['id' => self::ROLLE_GELOESCHT, 'role' => 'admin-alt', 'role_display' => 'Gelöscht', 'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => $jetzt],
        ]);

        foreach (['a', 'b', 'c'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => strtoupper($recht),
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
        // Die gelöschte Rolle hatte alle Rechte
        foreach ([1, 2, 3] as $permissionId) {
            DB::table('role_permission')->insert(['role_id' => self::ROLLE_GELOESCHT, 'permission_id' => $permissionId]);
        }

        Route::middleware(['web', 'hasPermission:a'])->get('/_t/permission', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermissionAnd:a,b'])->get('/_t/and', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermissionOr:a,b'])->get('/_t/or', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasRole:admin'])->get('/_t/role', fn () => self::GEHEIM);
    }

    /**
     * @return array<string, array{0: int|string|null}>
     */
    public static function ungueltigeRollen(): array
    {
        return [
            'role_id null' => [null],
            'role_id 0' => [0],
            'role_id -1' => [-1],
            'nicht existierende Rolle' => [999],
            'gelöschte Rolle' => [self::ROLLE_GELOESCHT],
        ];
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function privilegierteRollen(): array
    {
        return [
            'Dev (1)' => [1],
            'Admin (2)' => [2],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function listenSchreibweisen(): array
    {
        return [
            'Komma-Liste' => ["'a,b'"],
            'Komma-Liste mit Leerzeichen' => ["'a, b'"],
            'Array' => ["['a','b']"],
        ];
    }

    private function nutzerMitRolle(int|string|null $roleId): User
    {
        if ($roleId === null) {
            // Die Spalte erlaubt kein NULL; ein Nutzer ohne Rolle existiert nur im Speicher (z. B. Fremd-Model/Host).
            return User::factory()->make(['role_id' => null]);
        }

        return User::factory()->create(['role_id' => $roleId]);
    }

    /**
     * @param  list<string>  $rechte
     */
    private function normalerNutzerMitRechten(array $rechte): User
    {
        $user = User::factory()->create(['role_id' => self::ROLLE_NORMAL]);
        foreach ($rechte as $recht) {
            DB::table('role_permission')->insert([
                'role_id' => self::ROLLE_NORMAL,
                'permission_id' => DB::table('permissions')->where('permission', $recht)->value('id'),
            ]);
        }

        return $user;
    }

    private function assertKeinZugriff(string $url, User $user): void
    {
        $response = $this->actingAs($user)->get($url);

        $this->assertNotSame(200, $response->getStatusCode(), "{$url} darf nicht 200 liefern");
        $this->assertLessThan(500, $response->getStatusCode(), "{$url} darf keinen Serverfehler liefern");
        $this->assertStringNotContainsString(self::GEHEIM, (string) $response->getContent());
    }

    private function assertZugriff(string $url, User $user): void
    {
        $this->actingAs($user)->get($url)->assertOk()->assertSee(self::GEHEIM);
    }

    private function rendere(string $vorlage, User $user): string
    {
        $this->actingAs($user);

        return trim(Blade::render($vorlage));
    }

    // ---------------------------------------------------------------- AK11

    // AK11
    #[DataProvider('ungueltigeRollen')]
    public function test_ungueltige_rolle_hat_keinen_zugriff_ueber_has_permission(int|string|null $roleId): void
    {
        $this->assertKeinZugriff('/_t/permission', $this->nutzerMitRolle($roleId));
    }

    // AK11
    #[DataProvider('ungueltigeRollen')]
    public function test_ungueltige_rolle_hat_keinen_zugriff_ueber_has_permission_and(int|string|null $roleId): void
    {
        $this->assertKeinZugriff('/_t/and', $this->nutzerMitRolle($roleId));
    }

    // AK11
    #[DataProvider('ungueltigeRollen')]
    public function test_ungueltige_rolle_hat_keinen_zugriff_ueber_has_permission_or(int|string|null $roleId): void
    {
        $this->assertKeinZugriff('/_t/or', $this->nutzerMitRolle($roleId));
    }

    // AK11
    #[DataProvider('ungueltigeRollen')]
    public function test_ungueltige_rolle_hat_keinen_zugriff_ueber_has_role(int|string|null $roleId): void
    {
        $this->assertKeinZugriff('/_t/role', $this->nutzerMitRolle($roleId));
    }

    // AK11
    #[DataProvider('ungueltigeRollen')]
    public function test_ungueltige_rolle_wird_vom_gate_abgelehnt(int|string|null $roleId): void
    {
        $this->actingAs($this->nutzerMitRolle($roleId));

        $this->assertFalse(Gate::allows('beliebig'));
        $this->assertTrue(Gate::denies('a'));
    }

    // AK11
    #[DataProvider('ungueltigeRollen')]
    public function test_ungueltige_rolle_wird_von_blade_can_abgelehnt(int|string|null $roleId): void
    {
        $ausgabe = $this->rendere("@can('beliebig') JA @else NEIN @endcan", $this->nutzerMitRolle($roleId));

        $this->assertSame('NEIN', $ausgabe);
    }

    // ---------------------------------------------------------------- AK12 (Regressionsschutz, heute schon grün)

    // AK12
    #[DataProvider('privilegierteRollen')]
    public function test_dev_und_admin_haben_zugriff_auf_alle_geschuetzten_routen(int $roleId): void
    {
        $user = $this->nutzerMitRolle($roleId);

        foreach (['/_t/permission', '/_t/and', '/_t/or', '/_t/role'] as $url) {
            $this->assertZugriff($url, $user);
        }
    }

    // AK12
    #[DataProvider('privilegierteRollen')]
    public function test_dev_und_admin_bestehen_gate_pruefungen(int $roleId): void
    {
        $this->actingAs($this->nutzerMitRolle($roleId));

        $this->assertTrue(Gate::allows('beliebig'));
    }

    // AK12
    #[DataProvider('privilegierteRollen')]
    public function test_dev_und_admin_bestehen_blade_can(int $roleId): void
    {
        $ausgabe = $this->rendere("@can('beliebig') JA @else NEIN @endcan", $this->nutzerMitRolle($roleId));

        $this->assertSame('JA', $ausgabe);
    }

    // ---------------------------------------------------------------- AK13 Middleware

    // AK13
    public function test_has_permission_and_verweigert_wenn_nur_ein_recht_vorhanden_ist(): void
    {
        $this->assertKeinZugriff('/_t/and', $this->normalerNutzerMitRechten(['a']));
    }

    // AK13
    public function test_has_permission_and_erlaubt_wenn_beide_rechte_vorhanden_sind(): void
    {
        $this->assertZugriff('/_t/and', $this->normalerNutzerMitRechten(['a', 'b']));
    }

    // AK13
    public function test_has_permission_and_verweigert_ohne_jedes_recht(): void
    {
        $this->assertKeinZugriff('/_t/and', $this->normalerNutzerMitRechten([]));
    }

    // AK13
    public function test_has_permission_or_erlaubt_mit_einem_der_rechte(): void
    {
        $this->assertZugriff('/_t/or', $this->normalerNutzerMitRechten(['a']));
    }

    // AK13
    public function test_has_permission_or_erlaubt_mit_dem_zweiten_recht(): void
    {
        $this->assertZugriff('/_t/or', $this->normalerNutzerMitRechten(['b']));
    }

    // AK13
    public function test_has_permission_or_erlaubt_mit_beiden_rechten(): void
    {
        $this->assertZugriff('/_t/or', $this->normalerNutzerMitRechten(['a', 'b']));
    }

    // AK13
    public function test_has_permission_or_verweigert_ohne_eines_der_rechte(): void
    {
        $this->assertKeinZugriff('/_t/or', $this->normalerNutzerMitRechten([]));
    }

    // AK13
    public function test_has_permission_or_verweigert_mit_nur_fremdem_recht(): void
    {
        $this->assertKeinZugriff('/_t/or', $this->normalerNutzerMitRechten(['c']));
    }

    // ---------------------------------------------------------------- AK13 Blade-Direktiven

    // AK13
    #[DataProvider('listenSchreibweisen')]
    public function test_blade_has_permission_and_verweigert_bei_nur_einem_recht(string $liste): void
    {
        $ausgabe = $this->rendere("@hasPermissionAnd({$liste}) JA @else NEIN @endhasPermissionAnd", $this->normalerNutzerMitRechten(['a']));

        $this->assertSame('NEIN', $ausgabe);
    }

    // AK13
    #[DataProvider('listenSchreibweisen')]
    public function test_blade_has_permission_and_erlaubt_bei_beiden_rechten(string $liste): void
    {
        $ausgabe = $this->rendere("@hasPermissionAnd({$liste}) JA @else NEIN @endhasPermissionAnd", $this->normalerNutzerMitRechten(['a', 'b']));

        $this->assertSame('JA', $ausgabe);
    }

    // AK13
    #[DataProvider('listenSchreibweisen')]
    public function test_blade_has_permission_or_erlaubt_bei_einem_recht(string $liste): void
    {
        $ausgabe = $this->rendere("@hasPermissionOr({$liste}) JA @else NEIN @endhasPermissionOr", $this->normalerNutzerMitRechten(['a']));

        $this->assertSame('JA', $ausgabe);
    }

    // AK13
    #[DataProvider('listenSchreibweisen')]
    public function test_blade_has_permission_or_verweigert_ohne_recht(string $liste): void
    {
        $ausgabe = $this->rendere("@hasPermissionOr({$liste}) JA @else NEIN @endhasPermissionOr", $this->normalerNutzerMitRechten([]));

        $this->assertSame('NEIN', $ausgabe);
    }
}
