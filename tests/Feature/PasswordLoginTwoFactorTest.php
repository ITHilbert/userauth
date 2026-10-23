<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use ITHilbert\UserAuth\Tests\Support\NachrichtenAbfangen;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Regressionsschutz für die Auslösung der 2FA nach dem Passwort-Login (CompleteAuthentication).
 * Der Test "mit 2FA" prüft seit userauth-haertung das neue Verhalten (AK14): nach dem Passwort noch nicht angemeldet,
 * der Code kommt per Mail und steht nicht in `users.two_factor_code` (AK18).
 */
final class PasswordLoginTwoFactorTest extends TestCase
{
    use NachrichtenAbfangen;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nachrichtenAbfangen();
        config()->set('userauth.redirect_after_login', '/');
    }

    // AK21
    public function test_password_login_without_two_factor_redirects_to_target(): void
    {
        config()->set('userauth.two_factor_enabled', false);
        $user = config('auth.providers.users.model')::factory()->create(['email' => 'max@firma.de']);

        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $this->gesendeteNachrichten());
    }

    // AK14
    public function test_password_login_with_two_factor_requires_code(): void
    {
        config()->set('userauth.two_factor_enabled', true);
        config('auth.providers.users.model')::factory()->create(['email' => 'max@firma.de']);

        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])
            ->assertRedirect(route('verify.index'));

        $this->assertGuest();
        $this->assertCount(1, $this->nachrichtenAn('max@firma.de'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->zweiFaktorCode('max@firma.de'));
    }

    // AK18
    public function test_two_factor_code_is_not_stored_in_users_table(): void
    {
        config()->set('userauth.two_factor_enabled', true);
        $user = config('auth.providers.users.model')::factory()->create(['email' => 'max@firma.de']);

        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password']);
        $code = $this->zweiFaktorCode('max@firma.de');

        $gespeichert = $user->fresh()->getAttributes()['two_factor_code'] ?? null;
        $this->assertNotSame($code, $gespeichert, 'Der Klartext-Code steht in users.two_factor_code.');
    }
}
