<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * userauth-nur-admin (Dashboard #357), AK7: Löschschutz der Rolle `admin` über den Schlüssel, nicht über die ID.
 *
 * Aufbau: Der Bediener hat die gewöhnliche Rolle `verwalter` (ID 50) mit den Rechten role_read/_edit/_delete,
 * ist also selbst nicht privilegiert und die Sperre kommt nur aus dem Schutz der Rolle.
 */
final class NurAdminRollenschutzTest extends TestCase
{
    use RefreshDatabase;

    private const VERWALTER = 50;

    private const MELDUNG = 'Die Rolle Admin kann nicht gelöscht werden.';

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        View::getFinder()->prependLocation(__DIR__.'/../Fixtures/adminviews');
        config(['laravelkit.theme' => 'tailwind']);
        app()->setLocale('de');

        $jetzt = now();
        DB::table('permissions_groups')->insert(['id' => 1, 'group_name' => 'role', 'group_display' => 'Role', 'is_group' => 0, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        DB::table('roles')->insert(['id' => self::VERWALTER, 'role' => 'verwalter', 'role_display' => 'Verwalter', 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        foreach (['read', 'edit', 'delete'] as $art) {
            $id = DB::table('permissions')->insertGetId([
                'permission' => 'role_'.$art, 'permission_display' => 'Rolle '.$art, 'group_id' => 1, 'crud' => $art,
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            DB::table('role_permission')->insert(['role_id' => self::VERWALTER, 'permission_id' => $id]);
        }
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
    }

    private function rolle(int $id, string $schluessel): void
    {
        DB::table('roles')->insert(['id' => $id, 'role' => $schluessel, 'role_display' => ucfirst($schluessel), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function bediener(): User
    {
        return User::factory()->create(['role_id' => self::VERWALTER]);
    }

    private function geloescht(int $id): bool
    {
        return DB::table('roles')->where('id', $id)->whereNotNull('deleted_at')->exists();
    }

    /** @return array<string, array{0: int}> */
    public static function adminIds(): array
    {
        return ['id 1' => [1], 'id 2' => [2], 'id 7' => [7]];
    }

    /** @return array<string, array{0: string}> Zeile `action` je Rolle der Liste (DataTables, Ajax) */
    private function aktionen(): array
    {
        $antwort = $this->actingAs($this->bediener())->getJson(route('role'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $aktionen = [];
        foreach ($antwort->json('data') as $zeile) {
            $aktionen[$zeile['role']] = $zeile['action'];
        }

        return $aktionen;
    }

    // AK7
    #[DataProvider('adminIds')]
    public function test_das_loeschen_der_rolle_admin_wird_abgelehnt_egal_welche_id_sie_hat(int $id): void
    {
        $this->rolle($id, 'admin');
        $this->rolle(60, 'gewoehnlich');
        $url = route('role');

        // Gegenprobe: der Aufbau greift, eine gewöhnliche Rolle lässt sich über dieselbe Route löschen
        $this->actingAs($this->bediener())->from($url)->delete(route('role.delete', 60))->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertTrue($this->geloescht(60), 'Gegenprobe: gewöhnliche Rolle gelöscht');

        $this->from($url)->delete(route('role.delete', $id))->assertRedirect($url)->assertSessionHasErrors();
        $this->assertSame(self::MELDUNG, session('errors')->first());
        $this->assertFalse($this->geloescht($id));
        $this->assertSame(1, DB::table('roles')->where('id', $id)->where('role', 'admin')->count());
    }

    // AK7
    public function test_die_meldung_beim_loeschen_der_rolle_admin_steht_in_der_gerenderten_seite(): void
    {
        $this->rolle(7, 'admin');
        $url = route('role');

        $this->actingAs($this->bediener())->from($url)->delete(route('role.delete', 7))->assertSessionHasErrors();
        $meldung = session('errors')->first();

        $this->from($url)->followingRedirects()->delete(route('role.delete', 7))->assertOk()->assertSee(e($meldung), false);
        $this->assertFalse($this->geloescht(7));
    }

    // AK7
    #[DataProvider('adminIds')]
    public function test_die_liste_zeigt_fuer_admin_weder_aendern_noch_loeschen_fuer_andere_rollen_schon(int $id): void
    {
        $this->rolle($id, 'admin');
        $this->rolle(60, 'gewoehnlich');

        $aktionen = $this->aktionen();

        // Gegenprobe: eine gewöhnliche Rolle hat beide Schaltflächen
        $this->assertStringContainsString(route('role.edit', 60), $aktionen['gewoehnlich']);
        $this->assertStringContainsString('delete', strtolower($aktionen['gewoehnlich']));

        $this->assertStringNotContainsString(route('role.edit', $id), $aktionen['admin']);
        $this->assertStringNotContainsString('<a', $aktionen['admin']);
        $this->assertStringNotContainsString('<button', $aktionen['admin']);
        // einzelwort: geprüft wird nur die Aktionszelle der Zeile (leerer Container), nicht die ganze Seite
        $this->assertStringNotContainsString('delete', strtolower($aktionen['admin']));
    }

    // AK7
    public function test_dev_auch_mit_id_1_laesst_sich_loeschen_und_hat_schaltflaechen(): void
    {
        $this->rolle(1, 'dev');
        $this->rolle(7, 'admin');

        $aktionen = $this->aktionen();
        $this->assertStringContainsString(route('role.edit', 1), $aktionen['dev']);
        $this->assertStringContainsString('delete', strtolower($aktionen['dev']));
        // Gegenprobe: admin ist weiterhin gesperrt
        $this->assertStringNotContainsString(route('role.edit', 7), $aktionen['admin']);

        $this->actingAs($this->bediener())->delete(route('role.delete', 1))->assertSessionHasNoErrors();
        $this->assertTrue($this->geloescht(1));
        $this->assertFalse($this->geloescht(7));
    }

    // AK7
    public function test_eine_rolle_mit_id_2_ohne_schluessel_admin_laesst_sich_loeschen_und_hat_schaltflaechen(): void
    {
        $this->rolle(2, 'redakteur');
        $this->rolle(7, 'admin');

        $aktionen = $this->aktionen();
        $this->assertStringContainsString(route('role.edit', 2), $aktionen['redakteur']);
        $this->assertStringContainsString('delete', strtolower($aktionen['redakteur']));
        // Gegenprobe: admin ist weiterhin gesperrt
        $this->assertStringNotContainsString(route('role.edit', 7), $aktionen['admin']);

        $this->actingAs($this->bediener())->delete(route('role.delete', 2))->assertSessionHasNoErrors();
        $this->assertTrue($this->geloescht(2));
        $this->assertFalse($this->geloescht(7));
    }
}
