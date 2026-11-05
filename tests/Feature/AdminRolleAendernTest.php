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
 * userauth-admin-nachzug (Dashboard #662), AK1 und Randfälle 1 und 2: Die Rolle mit Schlüssel `admin` lässt sich
 * über role.edit und role.update serverseitig nicht ändern, jede andere Rolle schon.
 *
 * Aufbau: Der Bediener hat die gewöhnliche Rolle `verwalter` (ID 50) mit role_read/_edit, ist also selbst nicht
 * privilegiert; die Sperre kommt nur aus dem Schutz der Rolle.
 */
final class AdminRolleAendernTest extends TestCase
{
    use RefreshDatabase;

    private const VERWALTER = 50;

    private const MELDUNG = 'Die Rolle Admin kann nicht geändert werden.';

    private int $rechtId = 0;

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
        foreach (['read', 'edit'] as $art) {
            $id = DB::table('permissions')->insertGetId([
                'permission' => 'role_'.$art, 'permission_display' => 'Rolle '.$art, 'group_id' => 1, 'crud' => $art,
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            DB::table('role_permission')->insert(['role_id' => self::VERWALTER, 'permission_id' => $id]);
            $this->rechtId = $id;
        }
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
    }

    private function rolle(int $id, string $schluessel, bool $geloescht = false): void
    {
        DB::table('roles')->insert([
            'id' => $id, 'role' => $schluessel, 'role_display' => ucfirst($schluessel),
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $geloescht ? now() : null,
        ]);
        DB::table('role_permission')->insert(['role_id' => $id, 'permission_id' => $this->rechtId]);
    }

    private function bediener(): User
    {
        return User::factory()->create(['role_id' => self::VERWALTER]);
    }

    /** @return array{role: string, role_display: string, rechte: list<int>} */
    private function zustand(int $id): array
    {
        $zeile = DB::table('roles')->where('id', $id)->first();

        return [
            'role' => (string) $zeile->role,
            'role_display' => (string) $zeile->role_display,
            'rechte' => DB::table('role_permission')->where('role_id', $id)->pluck('permission_id')->map(intval(...))->sort()->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function formular(string $schluessel, string $anzeige, array $rechte): array
    {
        return ['role' => $schluessel, 'role_display' => $anzeige, 'permission' => $rechte];
    }

    /** @return array<string, array{0: int}> */
    public static function adminIds(): array
    {
        return ['id 1' => [1], 'id 2' => [2], 'id 7' => [7]];
    }

    // AK1
    #[DataProvider('adminIds')]
    public function test_das_bearbeiten_der_rolle_admin_wird_abgelehnt_egal_welche_id_sie_hat(int $id): void
    {
        $this->rolle($id, 'admin');
        $this->rolle(60, 'gewoehnlich');
        $url = route('role');

        // Gegenprobe: der Aufbau greift, eine gewöhnliche Rolle ist über dieselbe Route erreichbar
        $this->actingAs($this->bediener())->from($url)->get(route('role.edit', 60))->assertOk();

        $this->from($url)->get(route('role.edit', $id))->assertRedirect($url)->assertSessionHasErrors();
        $this->assertSame(self::MELDUNG, session('errors')->first());
    }

    // AK1
    #[DataProvider('adminIds')]
    public function test_das_speichern_der_rolle_admin_wird_abgelehnt_und_aendert_nichts(int $id): void
    {
        $this->rolle($id, 'admin');
        $this->rolle(60, 'gewoehnlich');
        $url = route('role');
        $vorher = $this->zustand($id);

        // Gegenprobe: eine gewöhnliche Rolle lässt sich über dieselbe Route speichern
        $this->actingAs($this->bediener())->from($url)->post(route('role.update', 60), $this->formular('gewoehnlich', 'Umbenannt', [$this->rechtId]))
            ->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertSame('Umbenannt', $this->zustand(60)['role_display']);

        $this->from($url)->post(route('role.update', $id), $this->formular('hacker', 'Gehackt', []))->assertRedirect($url)->assertSessionHasErrors();
        $this->assertSame(self::MELDUNG, session('errors')->first());
        $this->assertSame($vorher, $this->zustand($id));
    }

    // AK1
    public function test_die_meldung_beim_bearbeiten_der_rolle_admin_steht_in_der_gerenderten_seite(): void
    {
        $this->rolle(7, 'admin');
        $url = route('role');

        $this->actingAs($this->bediener())->from($url)->get(route('role.edit', 7))->assertSessionHasErrors();
        $meldung = session('errors')->first();
        $this->assertSame(self::MELDUNG, $meldung);

        $this->from($url)->followingRedirects()->get(route('role.edit', 7))->assertOk()->assertSee(e($meldung), false);
    }

    // AK1
    public function test_die_meldung_beim_speichern_der_rolle_admin_steht_in_der_gerenderten_seite(): void
    {
        $this->rolle(7, 'admin');
        $url = route('role');
        $daten = $this->formular('admin', 'Admin', [$this->rechtId]);

        $this->actingAs($this->bediener())->from($url)->post(route('role.update', 7), $daten)->assertSessionHasErrors();
        $meldung = session('errors')->first();
        $this->assertSame(self::MELDUNG, $meldung);

        $this->from($url)->followingRedirects()->post(route('role.update', 7), $daten)->assertOk()->assertSee(e($meldung), false);
    }

    // AK1
    public function test_die_englische_meldung_ist_not_allowed(): void
    {
        $this->rolle(7, 'admin');
        app()->setLocale('en');
        $url = route('role');

        $this->actingAs($this->bediener())->from($url)->get(route('role.edit', 7))->assertSessionHasErrors();
        $this->assertSame('Not allowed.', session('errors')->first());
    }

    // AK1
    public function test_dev_mit_id_1_laesst_sich_bearbeiten_und_speichern(): void
    {
        $this->rolle(1, 'dev');
        $this->rolle(7, 'admin');
        $url = route('role');

        // Gegenprobe: admin ist weiterhin gesperrt
        $this->actingAs($this->bediener())->from($url)->get(route('role.edit', 7))->assertSessionHasErrors();

        $this->from($url)->get(route('role.edit', 1))->assertOk();
        $this->from($url)->post(route('role.update', 1), $this->formular('dev', 'Entwickler', [$this->rechtId]))->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertSame('Entwickler', $this->zustand(1)['role_display']);
    }

    // AK1
    public function test_eine_rolle_mit_id_2_ohne_schluessel_admin_laesst_sich_bearbeiten_und_speichern(): void
    {
        $this->rolle(2, 'redakteur');
        $this->rolle(7, 'admin');
        $url = route('role');

        // Gegenprobe: admin ist weiterhin gesperrt
        $this->actingAs($this->bediener())->from($url)->post(route('role.update', 7), $this->formular('admin', 'Admin', []))->assertSessionHasErrors();

        $this->from($url)->get(route('role.edit', 2))->assertOk();
        $this->from($url)->post(route('role.update', 2), $this->formular('redakteur', 'Redaktion', [$this->rechtId]))->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertSame('Redaktion', $this->zustand(2)['role_display']);
    }

    // Randfall 1
    public function test_post_auf_update_der_rolle_admin_mit_unveraendertem_schluessel_wird_abgelehnt(): void
    {
        $this->rolle(7, 'admin');
        $url = route('role');
        $vorher = $this->zustand(7);

        // Gegenprobe: dieselbe Art Anfrage (unveränderter Schlüssel) wird bei einer gewöhnlichen Rolle gespeichert
        $this->rolle(60, 'gewoehnlich');
        $this->actingAs($this->bediener())->from($url)->post(route('role.update', 60), $this->formular('gewoehnlich', 'Neuer Name', [$this->rechtId]))->assertSessionHasNoErrors();
        $this->assertSame('Neuer Name', $this->zustand(60)['role_display']);

        $this->from($url)->post(route('role.update', 7), $this->formular('admin', 'Admin', []))->assertRedirect($url)->assertSessionHasErrors();
        $this->assertSame(self::MELDUNG, session('errors')->first());
        $this->assertSame($vorher, $this->zustand(7));
    }

    // Randfall 2
    public function test_weich_geloeschte_rolle_admin_ist_nicht_gesperrt_sondern_wie_jede_geloeschte_rolle_nicht_gefunden(): void
    {
        $this->rolle(7, 'admin', geloescht: true);
        $this->rolle(8, 'gewoehnlich', geloescht: true);
        $url = route('role');
        $vorher = $this->zustand(7);

        // Gegenprobe: eine gewöhnliche gelöschte Rolle verhält sich so (404), die Route ist erreichbar und der Bediener berechtigt
        $this->actingAs($this->bediener())->from($url)->get(route('role.edit', 8))->assertNotFound();

        $this->from($url)->get(route('role.edit', 7))->assertNotFound();
        $this->from($url)->post(route('role.update', 7), $this->formular('admin', 'Admin', []))->assertNotFound();
        $this->assertSame($vorher, $this->zustand(7));
    }
}
