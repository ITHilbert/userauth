<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Nachbesserung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Ungültige Werte für Stundensatz und Geburtstag. Abgedeckt: AK7 (userauth-nachbesserung).
 */
final class GueltigeWerteTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../../Fixtures/views');

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => self::ROLLE_NORMAL, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
        foreach (['user_create', 'user_edit'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            DB::table('role_permission')->insert(['role_id' => self::ROLLE_NORMAL, 'permission_id' => $i + 1]);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function ungueltigeWerte(): array
    {
        return [
            'stundensatz text' => ['hourly_rate', 'abc'],
            'stundensatz negativ' => ['hourly_rate', '-5'],
            'stundensatz eine Million' => ['hourly_rate', '1000000'],
            'stundensatz knapp über Obergrenze' => ['hourly_rate', '999999.991'],
            'stundensatz sehr groß' => ['hourly_rate', '99999999999'],
            'geburtstag 31. Februar' => ['birthday', '2020-02-31'],
            'geburtstag text' => ['birthday', 'abc'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function gueltigeWerte(): array
    {
        return [
            'stundensatz' => ['hourly_rate', '12.50'],
            'stundensatz Obergrenze' => ['hourly_rate', '999999.99'],
            'stundensatz null' => ['hourly_rate', '0'],
            'geburtstag' => ['birthday', '1985-03-09'],
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_NORMAL]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formularDaten(array $extra = []): array
    {
        return array_merge([
            'name' => 'Neuer Nutzer',
            'email' => 'neu@example.test',
            'password' => 'Ein-Passwort-456!',
            'password2' => 'Ein-Passwort-456!',
            'role_id' => self::ROLLE_NORMAL,
        ], $extra);
    }

    private function bestehenderNutzer(): User
    {
        $user = User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'alt@example.test', 'name' => 'Alter Name']);
        DB::table('users')->where('id', $user->id)->update(['hourly_rate' => 45.5, 'birthday' => '1990-05-17']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function updateDaten(array $extra): array
    {
        return $this->formularDaten(['email' => 'alt@example.test', 'name' => 'Neuer Name', 'password' => '', 'password2' => ''] + $extra);
    }

    // AK7
    #[DataProvider('gueltigeWerte')]
    public function test_ak7_gegenprobe_store_nimmt_gueltigen_wert_an(string $feld, string $wert): void
    {
        $this->actingAs($this->admin())
            ->post('/users/store', $this->formularDaten([$feld => $wert]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user'));

        $this->assertDatabaseHas('users', ['email' => 'neu@example.test']);
    }

    // AK7
    #[DataProvider('gueltigeWerte')]
    public function test_ak7_gegenprobe_update_nimmt_gueltigen_wert_an(string $feld, string $wert): void
    {
        $ziel = $this->bestehenderNutzer();

        $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->updateDaten([$feld => $wert]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Neuer Name', DB::table('users')->where('id', $ziel->id)->value('name'));
    }

    // AK7
    #[DataProvider('ungueltigeWerte')]
    public function test_ak7_store_mit_ungueltigem_wert_gibt_validierungsfehler_und_legt_nichts_an(string $feld, string $wert): void
    {
        $admin = $this->admin();
        $vorher = DB::table('users')->count();

        $antwort = $this->actingAs($admin)->post('/users/store', $this->formularDaten([$feld => $wert]));

        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein Serverfehler');
        $antwort->assertSessionHasErrors([$feld]);
        $this->assertSame([$feld], array_keys(session('errors')->getBag('default')->toArray()), 'Fehler nur für dieses Feld');
        $this->assertSame($vorher, DB::table('users')->count());
        $this->assertDatabaseMissing('users', ['email' => 'neu@example.test']);
    }

    // AK7
    #[DataProvider('ungueltigeWerte')]
    public function test_ak7_update_mit_ungueltigem_wert_gibt_validierungsfehler_und_laesst_nutzer_unveraendert(string $feld, string $wert): void
    {
        $ziel = $this->bestehenderNutzer();
        $vorher = (array) DB::table('users')->where('id', $ziel->id)->first();

        $antwort = $this->actingAs($this->admin())->post('/users/update/'.$ziel->id, $this->updateDaten([$feld => $wert]));

        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein Serverfehler');
        $antwort->assertSessionHasErrors([$feld]);
        $this->assertSame([$feld], array_keys(session('errors')->getBag('default')->toArray()), 'Fehler nur für dieses Feld');
        $this->assertSame($vorher, (array) DB::table('users')->where('id', $ziel->id)->first());
    }
}
