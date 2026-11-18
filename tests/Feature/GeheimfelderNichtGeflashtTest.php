<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Bugfix #1595: Bei fehlgeschlagener Validierung flasht das Paket keine Geheimfelder
 * (aktuelles_passwort, pwtoken, two_factor_code, password, password_confirmation) als _old_input.
 */
final class GeheimfelderNichtGeflashtTest extends TestCase
{
    use RefreshDatabase;

    private const AKTUELL = 'Aktuelles-Passwort-1!';

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        config()->set('userauth.password_policy.enabled', false);
        config()->set('userauth.two_factor_enabled', false);

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 3, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
    }

    /**
     * Gegenprobe: ein normales Feld wird bei Validierungsfehler geflasht (Flash-Aufbau greift).
     */
    private function normalesFeldWirdGeflasht(): void
    {
        $this->from('/x')->post(route('password.sendtocken'), ['email' => 'kein-email-format'])
            ->assertSessionHasErrors();
        $this->assertSame('kein-email-format', session()->getOldInput('email'), 'Gegenprobe: normales Feld wird geflasht');
    }

    // AK: Geheimfelder nie in die Sitzung flashen (Bugfix #1595)
    public function test_passwort_aendern_flasht_aktuelles_passwort_nicht(): void
    {
        $nutzer = User::factory()->create(['role_id' => 3, 'password' => Hash::make(self::AKTUELL)]);
        $this->normalesFeldWirdGeflasht();

        $this->actingAs($nutzer)->from(route('password.edit'))->post(route('password.update'), [
            'aktuelles_passwort' => self::AKTUELL,
            'password' => 'x',
            'password_confirmation' => 'y',
        ])->assertSessionHasErrors('password');

        $alt = session()->getOldInput();
        $this->assertArrayNotHasKey('aktuelles_passwort', $alt, '_old_input enthält aktuelles_passwort');
        $this->assertArrayNotHasKey('password', $alt);
        $this->assertArrayNotHasKey('password_confirmation', $alt);
    }

    // AK: Geheimfelder nie in die Sitzung flashen (Bugfix #1595)
    public function test_passwort_zuruecksetzen_flasht_pwtoken_nicht(): void
    {
        $this->normalesFeldWirdGeflasht();

        $this->from('/x')->post(route('password.updatewithtoken'), [
            'email' => 'max@firma.de',
            'pwtoken' => 'geheimer-token-123',
            'password' => 'x',
            'password_confirmation' => 'y',
        ])->assertSessionHasErrors('password');

        $alt = session()->getOldInput();
        $this->assertSame('max@firma.de', $alt['email'] ?? null, 'Gegenprobe: email wird geflasht');
        $this->assertArrayNotHasKey('pwtoken', $alt, '_old_input enthält pwtoken');
    }

    // AK: Geheimfelder nie in die Sitzung flashen (Bugfix #1595)
    public function test_zwei_faktor_code_wird_bei_validierungsfehler_nicht_geflasht(): void
    {
        $this->normalesFeldWirdGeflasht();

        // 21 Zeichen verletzen max:20 -> Validierungsfehler des FormRequests.
        $this->from('/x')->post(route('verify.store'), ['two_factor_code' => str_repeat('1', 21)])
            ->assertSessionHasErrors('two_factor_code');

        $this->assertArrayNotHasKey('two_factor_code', session()->getOldInput(), '_old_input enthält two_factor_code');
    }
}
