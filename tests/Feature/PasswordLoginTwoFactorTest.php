<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use ITHilbert\UserAuth\Mail\TwoFactorCode;
use Tests\TestCase;

/**
 * Regressionsschutz für das Auslagern der 2FA-Auslösung in CompleteAuthentication.
 */
final class PasswordLoginTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config()->set('userauth.redirect_after_login', '/');
    }

    public function test_password_login_without_two_factor_redirects_to_target(): void
    {
        config()->set('userauth.two_factor_enabled', false);
        $user = config('auth.providers.users.model')::factory()->create(['email' => 'max@firma.de']);

        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
    }

    public function test_password_login_with_two_factor_requires_code(): void
    {
        config()->set('userauth.two_factor_enabled', true);
        $user = config('auth.providers.users.model')::factory()->create(['email' => 'max@firma.de']);

        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])
            ->assertRedirect(route('verify.index'));

        $this->assertNotNull($user->fresh()->two_factor_code);
        Mail::assertSent(TwoFactorCode::class, fn ($mail) => $mail->hasTo('max@firma.de'));
    }
}
