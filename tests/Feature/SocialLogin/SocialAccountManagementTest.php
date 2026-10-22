<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

use Illuminate\Support\Facades\Mail;
use ITHilbert\UserAuth\Mail\SocialAccountLinked;

final class SocialAccountManagementTest extends SocialLoginTestCase
{
    public function test_logged_in_user_can_connect_google_account_with_different_email(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de'], verified: false);
        $this->provider->returnIdentity('google-sub-1', 'max.privat@gmail.com');

        $this->actingAs($user)
            ->get(route('userauth.social.callback', 'google'))
            ->assertRedirect(route('userauth.social.accounts'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $user->getKey(),
            'provider_user_id' => 'google-sub-1',
        ]);
        Mail::assertQueued(SocialAccountLinked::class, fn ($mail) => $mail->hasTo('max@firma.de'));
    }

    public function test_connect_is_rejected_if_google_account_belongs_to_other_user(): void
    {
        $other = $this->makeUser(['email' => 'andere@firma.de']);
        $this->linkGoogle($other, 'google-sub-1');
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->provider->returnIdentity('google-sub-1', 'andere@firma.de');

        $this->actingAs($user)
            ->get(route('userauth.social.callback', 'google'))
            ->assertRedirect(route('userauth.social.accounts'))
            ->assertSessionHasErrors('social');

        $this->assertDatabaseMissing('user_social_accounts', ['user_id' => $user->getKey()]);
    }

    public function test_connect_is_rejected_if_user_already_has_google_account(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->linkGoogle($user, 'google-sub-old');
        $this->provider->returnIdentity('google-sub-new', 'max@firma.de');

        $this->actingAs($user)
            ->get(route('userauth.social.callback', 'google'))
            ->assertSessionHasErrors('social');

        $this->assertDatabaseMissing('user_social_accounts', ['provider_user_id' => 'google-sub-new']);
    }

    public function test_connect_respects_domain_restriction(): void
    {
        config()->set('userauth.social.allowed_domains', ['firma.de']);
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->provider->returnIdentity('google-sub-1', 'max@gmail.com');

        $this->actingAs($user)
            ->get(route('userauth.social.callback', 'google'))
            ->assertSessionHasErrors('social');

        $this->assertDatabaseCount('user_social_accounts', 0);
    }

    public function test_user_can_disconnect_own_google_account(): void
    {
        config()->set('userauth.audit_log_enabled', true);
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->linkGoogle($user, 'google-sub-1');

        $this->actingAs($user)
            ->delete(route('userauth.social.disconnect', 'google'))
            ->assertRedirect(route('userauth.social.accounts'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('user_social_accounts', ['user_id' => $user->getKey()]);
        $this->assertDatabaseHas('user_auth_audit_logs', [
            'user_id' => $user->getKey(),
            'event' => 'social_unlinked',
        ]);
    }

    public function test_disconnect_only_affects_own_account(): void
    {
        $other = $this->makeUser(['email' => 'andere@firma.de']);
        $this->linkGoogle($other, 'google-sub-other');
        $user = $this->makeUser(['email' => 'max@firma.de']);

        $this->actingAs($user)->delete(route('userauth.social.disconnect', 'google'));

        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $other->getKey()]);
    }

    public function test_disconnect_requires_authentication(): void
    {
        $this->delete(route('userauth.social.disconnect', 'google'))->assertRedirect(route('login'));
    }
}
