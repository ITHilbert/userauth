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
 * Privilegierte Rollen: Dev nur Rolle 1 mit Schlüssel `dev`, Admin nur Rolle 2 mit Schlüssel `admin` (AK12);
 * bei aktivierten Teams gilt die Rolle des aktiven Teams (AK12a) – für Middlewares, Gate, Blade `@can`,
 * `isAdmin`/`isDev` und Impersonation.
 *
 * Jeder Test zeigt zuerst per Gegenprobe, dass der Aufbau greift (korrekte Rolle bzw. Teams aus → Zugriff),
 * und prüft erst danach die Verneinung. Ein Nutzer wird je Phase neu geladen: Die Zugriffsprüfung merkt sich
 * die Rolle je Nutzer-Objekt.
 */
final class PrivilegierteRollenTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_DEV = 1;

    private const ROLLE_ADMIN = 2;

    private const ROLLE_NORMAL = 3;

    /** Prüfungen, die geprüft werden; Wert: Zugriff ja/nein. */
    private const PRUEFUNGEN = ['permission', 'and', 'or', 'role', 'admin', 'dev', 'gate', 'can'];

    protected function setUp(): void
    {
        parent::setUp();

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => self::ROLLE_DEV, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => self::ROLLE_ADMIN, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => self::ROLLE_NORMAL, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
        foreach (['a', 'b', 'impersonate_users'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }

        Route::middleware(['web', 'hasPermission:b'])->get('/_t/permission', fn () => 'OK');
        Route::middleware(['web', 'hasPermissionAnd:a,b'])->get('/_t/and', fn () => 'OK');
        Route::middleware(['web', 'hasPermissionOr:a,b'])->get('/_t/or', fn () => 'OK');
        Route::middleware(['web', 'hasRole:finanzen'])->get('/_t/role', fn () => 'OK');
        Route::middleware(['web', 'isAdmin'])->get('/_t/admin', fn () => 'OK');
        Route::middleware(['web', 'isDev'])->get('/_t/dev', fn () => 'OK');
    }

    // ---------------------------------------------------------------- Helfer

    /**
     * @return array<string, bool>
     */
    private function alleErlaubt(): array
    {
        return array_fill_keys(self::PRUEFUNGEN, true);
    }

    /**
     * @return array<string, bool>
     */
    private function nichtsErlaubt(): array
    {
        return array_fill_keys(self::PRUEFUNGEN, false);
    }

    /**
     * Admin: wie Dev, nur `isDev` bleibt verschlossen.
     *
     * @return array<string, bool>
     */
    private function adminErlaubt(): array
    {
        return array_merge($this->alleErlaubt(), ['dev' => false]);
    }

    /**
     * Nutzer frisch aus der Datenbank (eigene Rollenermittlung je Objekt).
     */
    private function nutzer(int $id): User
    {
        return User::query()->findOrFail($id);
    }

    private function nutzerMitRolle(int $roleId, ?int $teamRoleId = null): User
    {
        $user = User::factory()->create(['role_id' => $roleId]);

        if ($teamRoleId !== null) {
            $teamId = (int) DB::table('teams')->insertGetId(['name' => 'Team '.$user->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('team_user')->insert(['team_id' => $teamId, 'user_id' => $user->id, 'role_id' => $teamRoleId, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('id', $user->id)->update(['current_team_id' => $teamId]);
        }

        return $this->nutzer($user->id);
    }

    /**
     * Ergebnis aller Prüfungen für den Nutzer: Routen (Middlewares), Gate und Blade `@can`.
     *
     * @return array<string, bool>
     */
    private function pruefungen(User $user): array
    {
        $ergebnis = [];

        foreach (['permission', 'and', 'or', 'role', 'admin', 'dev'] as $name) {
            $response = $this->actingAs($user)->get('/_t/'.$name);
            $this->assertLessThan(500, $response->getStatusCode(), "/_t/{$name} darf keinen Serverfehler liefern");
            $ergebnis[$name] = $response->getStatusCode() === 200 && $response->getContent() === 'OK';
        }

        $this->actingAs($user);
        $ergebnis['gate'] = Gate::allows('beliebig');
        $ergebnis['can'] = trim(Blade::render("@can('beliebig') JA @else NEIN @endcan")) === 'JA';

        return $ergebnis;
    }

    private function assertPruefungen(array $erwartet, User $user, string $meldung = ''): void
    {
        $this->assertSame($erwartet, $this->pruefungen($user), $meldung);
    }

    /**
     * Statuscode der Übernahme von $ziel durch $handelnder (Sitzung vorher leer).
     */
    private function impersoniere(User $handelnder, User $ziel): int
    {
        $this->flushSession();

        $status = $this->actingAs($handelnder)->get(route('userauth.impersonate', $ziel->id))->getStatusCode();
        $this->assertLessThan(500, $status, 'Impersonation darf keinen Serverfehler liefern');

        return $status;
    }

    /**
     * Nutzer mit Recht `impersonate_users`, aber ohne Privilegien (Rolle 3).
     */
    private function rolleNormalDarfUebernehmen(): void
    {
        DB::table('role_permission')->insert(['role_id' => self::ROLLE_NORMAL, 'permission_id' => 3]);
    }

    private function teamsAktivieren(bool $aktiv): void
    {
        config(['userauth.teams.enabled' => $aktiv]);
    }

    // ---------------------------------------------------------------- AK12: ID und Schlüssel

    /**
     * Rollen, die nur auf den ersten Blick privilegiert sind: [Rolle des Nutzers, Umbenennungen (ID, Schlüssel) in Reihenfolge, neue Rollen (ID, Schlüssel)].
     *
     * @return array<string, array{0: int, 1: list<array{0: int, 1: string}>, 2: list<array{0: int, 1: string}>}>
     */
    public static function scheinprivilegierteRollen(): array
    {
        return [
            'Rolle 1 umbenannt' => [1, [[1, 'editor']], []],
            'Rolle 2 umbenannt' => [2, [[2, 'chef']], []],
            'Schlüssel dev bei ID 5' => [5, [[1, 'entwickler']], [[5, 'dev']]],
            'Schlüssel admin bei ID 6' => [6, [[2, 'verwalter']], [[6, 'admin']]],
            'Rolle 1 mit Schlüssel admin' => [1, [[2, 'verwalter'], [1, 'admin']], []],
            'Rolle 2 mit Schlüssel dev' => [2, [[1, 'entwickler'], [2, 'dev']], []],
        ];
    }

    /**
     * @param  list<array{0: int, 1: string}>  $umbenennungen
     * @param  list<array{0: int, 1: string}>  $neueRollen
     */
    private function rollenAendern(array $umbenennungen, array $neueRollen): void
    {
        foreach ($umbenennungen as [$id, $schluessel]) {
            DB::table('roles')->where('id', $id)->update(['role' => $schluessel]);
        }
        foreach ($neueRollen as [$id, $schluessel]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $schluessel, 'role_display' => $schluessel, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    // AK12
    #[DataProvider('scheinprivilegierteRollen')]
    public function test_nur_id_und_schluessel_zusammen_sind_privilegiert_auf_routen_gate_und_can(int $roleId, array $umbenennungen, array $neueRollen): void
    {
        $dev = $this->nutzerMitRolle(self::ROLLE_DEV);
        $admin = $this->nutzerMitRolle(self::ROLLE_ADMIN);
        $scheinprivilegiert = $this->nutzerMitRolle(self::ROLLE_NORMAL);

        // Gegenprobe: die korrekten Rollen 1 (dev) und 2 (admin) haben Zugriff, Rolle 3 nicht
        $this->assertPruefungen($this->alleErlaubt(), $dev, 'Gegenprobe Dev');
        $this->assertPruefungen($this->adminErlaubt(), $admin, 'Gegenprobe Admin');
        $this->assertPruefungen($this->nichtsErlaubt(), $scheinprivilegiert, 'Gegenprobe normale Rolle');

        $this->rollenAendern($umbenennungen, $neueRollen);
        DB::table('users')->where('id', $scheinprivilegiert->id)->update(['role_id' => $roleId]);

        $this->assertPruefungen($this->nichtsErlaubt(), $this->nutzer($scheinprivilegiert->id));
    }

    // AK12
    #[DataProvider('scheinprivilegierteRollen')]
    public function test_nur_id_und_schluessel_zusammen_duerfen_andere_uebernehmen(int $roleId, array $umbenennungen, array $neueRollen): void
    {
        $ziel = $this->nutzerMitRolle(self::ROLLE_NORMAL);
        $dev = $this->nutzerMitRolle(self::ROLLE_DEV);
        $admin = $this->nutzerMitRolle(self::ROLLE_ADMIN);
        $handelnder = $this->nutzerMitRolle(self::ROLLE_NORMAL);

        // Gegenprobe: die korrekten Rollen 1 und 2 dürfen übernehmen, Rolle 3 ohne das Recht nicht
        $this->assertSame(302, $this->impersoniere($dev, $ziel), 'Gegenprobe Dev darf übernehmen');
        $this->assertSame(302, $this->impersoniere($admin, $ziel), 'Gegenprobe Admin darf übernehmen');
        $this->assertSame(403, $this->impersoniere($handelnder, $ziel), 'Gegenprobe normale Rolle darf nicht übernehmen');

        $this->rollenAendern($umbenennungen, $neueRollen);
        DB::table('users')->where('id', $handelnder->id)->update(['role_id' => $roleId]);

        $this->assertSame(403, $this->impersoniere($this->nutzer($handelnder->id), $ziel));
        $this->assertFalse(session()->has('impersonated_by'), 'Es darf keine Übernahme stattgefunden haben');
    }

    // AK12
    #[DataProvider('scheinprivilegierteRollen')]
    public function test_nur_id_und_schluessel_zusammen_sind_nicht_uebernehmbar(int $roleId, array $umbenennungen, array $neueRollen): void
    {
        $this->rolleNormalDarfUebernehmen();
        $handelnder = $this->nutzerMitRolle(self::ROLLE_NORMAL);
        $dev = $this->nutzerMitRolle(self::ROLLE_DEV);
        $admin = $this->nutzerMitRolle(self::ROLLE_ADMIN);
        $ziel = $this->nutzerMitRolle(self::ROLLE_NORMAL);

        // Gegenprobe: der Handelnde darf eine normale Rolle übernehmen, aber weder Dev noch Admin
        $this->assertSame(302, $this->impersoniere($handelnder, $ziel), 'Gegenprobe normales Ziel');
        $this->assertSame(403, $this->impersoniere($handelnder, $dev), 'Gegenprobe Dev ist nicht übernehmbar');
        $this->assertSame(403, $this->impersoniere($handelnder, $admin), 'Gegenprobe Admin ist nicht übernehmbar');

        $this->rollenAendern($umbenennungen, $neueRollen);
        DB::table('users')->where('id', $ziel->id)->update(['role_id' => $roleId]);

        // Die umbenannte Rolle ist nicht privilegiert und damit übernehmbar
        $this->assertSame(302, $this->impersoniere($this->nutzer($handelnder->id), $this->nutzer($ziel->id)));
        $this->assertTrue(session()->has('impersonated_by'), 'Die Übernahme muss stattgefunden haben');
    }

    // ---------------------------------------------------------------- AK12a: Teams

    // AK12a
    public function test_bei_teams_zaehlt_die_teamrolle_nicht_die_globale_privilegierte_rolle(): void
    {
        $this->rolleNormalDarfUebernehmen();
        $globalAdmin = $this->nutzerMitRolle(self::ROLLE_ADMIN, self::ROLLE_NORMAL);
        $globalDev = $this->nutzerMitRolle(self::ROLLE_DEV, self::ROLLE_NORMAL);

        // Gegenprobe: ohne Teams gilt die globale Rolle
        $this->teamsAktivieren(false);
        $this->assertPruefungen($this->adminErlaubt(), $this->nutzer($globalAdmin->id), 'Gegenprobe Teams aus: Admin');
        $this->assertPruefungen($this->alleErlaubt(), $this->nutzer($globalDev->id), 'Gegenprobe Teams aus: Dev');

        $this->teamsAktivieren(true);
        $this->assertPruefungen($this->nichtsErlaubt(), $this->nutzer($globalAdmin->id), 'Teams an: globaler Admin, Teamrolle normal');
        $this->assertPruefungen($this->nichtsErlaubt(), $this->nutzer($globalDev->id), 'Teams an: globaler Dev, Teamrolle normal');
    }

    // AK12a
    public function test_bei_teams_gewaehrt_die_privilegierte_teamrolle_zugriff_trotz_normaler_globaler_rolle(): void
    {
        $teamAdmin = $this->nutzerMitRolle(self::ROLLE_NORMAL, self::ROLLE_ADMIN);
        $teamDev = $this->nutzerMitRolle(self::ROLLE_NORMAL, self::ROLLE_DEV);

        // Gegenprobe: ohne Teams gilt die globale Rolle 3 – kein Zugriff
        $this->teamsAktivieren(false);
        $this->assertPruefungen($this->nichtsErlaubt(), $this->nutzer($teamAdmin->id), 'Gegenprobe Teams aus: Team-Admin');
        $this->assertPruefungen($this->nichtsErlaubt(), $this->nutzer($teamDev->id), 'Gegenprobe Teams aus: Team-Dev');

        $this->teamsAktivieren(true);
        $this->assertPruefungen($this->adminErlaubt(), $this->nutzer($teamAdmin->id), 'Teams an: Team-Admin');
        $this->assertPruefungen($this->alleErlaubt(), $this->nutzer($teamDev->id), 'Teams an: Team-Dev');
    }

    // AK12a
    public function test_bei_teams_zaehlt_fuer_die_uebernahme_die_teamrolle_des_handelnden(): void
    {
        $ziel = $this->nutzerMitRolle(self::ROLLE_NORMAL);
        $globalAdminTeamNormal = $this->nutzerMitRolle(self::ROLLE_ADMIN, self::ROLLE_NORMAL);
        $globalNormalTeamAdmin = $this->nutzerMitRolle(self::ROLLE_NORMAL, self::ROLLE_ADMIN);

        // Gegenprobe: ohne Teams entscheidet die globale Rolle
        $this->teamsAktivieren(false);
        $this->assertSame(302, $this->impersoniere($this->nutzer($globalAdminTeamNormal->id), $ziel), 'Teams aus: globaler Admin darf');
        $this->assertSame(403, $this->impersoniere($this->nutzer($globalNormalTeamAdmin->id), $ziel), 'Teams aus: globale Rolle normal darf nicht');

        $this->teamsAktivieren(true);
        $this->assertSame(403, $this->impersoniere($this->nutzer($globalAdminTeamNormal->id), $ziel), 'Teams an: Teamrolle normal darf nicht');
        $this->assertSame(302, $this->impersoniere($this->nutzer($globalNormalTeamAdmin->id), $ziel), 'Teams an: Teamrolle Admin darf');
        $this->assertTrue(session()->has('impersonated_by'));
    }

    // AK12a
    public function test_bei_teams_zaehlt_fuer_die_uebernehmbarkeit_die_teamrolle_des_ziels(): void
    {
        $this->rolleNormalDarfUebernehmen();
        $handelnder = $this->nutzerMitRolle(self::ROLLE_NORMAL);
        $zielGlobalAdminTeamNormal = $this->nutzerMitRolle(self::ROLLE_ADMIN, self::ROLLE_NORMAL);
        $zielGlobalNormalTeamAdmin = $this->nutzerMitRolle(self::ROLLE_NORMAL, self::ROLLE_ADMIN);

        // Gegenprobe: ohne Teams entscheidet die globale Rolle des Ziels
        $this->teamsAktivieren(false);
        $this->assertSame(403, $this->impersoniere($this->nutzer($handelnder->id), $this->nutzer($zielGlobalAdminTeamNormal->id)), 'Teams aus: globaler Admin ist nicht übernehmbar');
        $this->assertSame(302, $this->impersoniere($this->nutzer($handelnder->id), $this->nutzer($zielGlobalNormalTeamAdmin->id)), 'Teams aus: globale Rolle normal ist übernehmbar');

        $this->teamsAktivieren(true);
        $this->assertSame(302, $this->impersoniere($this->nutzer($handelnder->id), $this->nutzer($zielGlobalAdminTeamNormal->id)), 'Teams an: Teamrolle normal ist übernehmbar');
        $this->assertSame(403, $this->impersoniere($this->nutzer($handelnder->id), $this->nutzer($zielGlobalNormalTeamAdmin->id)), 'Teams an: Team-Admin ist nicht übernehmbar');
    }
}
