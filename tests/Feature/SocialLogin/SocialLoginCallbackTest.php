<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use ITHilbert\UserAuth\Mail\SocialAccountLinked;
use ITHilbert\UserAuth\Mail\SocialLinkConfirmation;
use ITHilbert\UserAuth\Mail\TwoFactorCode;

final class SocialLoginCallbackTest extends SocialLoginTestCase
{
    public function test_already_linked_account_logs_in_by_provider_id(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->linkGoogle($user, 'google-sub-1');
        // E-Mail bei Google inzwischen geändert: Erkennung erfolgt über sub, nicht über E-Mail
        $this->provider->returnIdentity('google-sub-1', 'neue-adresse@gmail.com');

        $this->socialCallback()->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_verified_local_user_is_linked_automatically_and_logged_in(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de');

        $this->socialCallback()->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $user->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'google-sub-1',
        ]);
        Mail::assertQueued(SocialAccountLinked::class, fn ($mail) => $mail->hasTo('max@firma.de'));
    }

    public function test_email_matching_is_case_insensitive(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->provider->returnIdentity('google-sub-1', 'Max@Firma.DE');

        $this->socialCallback();

        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_local_user_gets_confirmation_mail_instead_of_login(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de'], verified: false);
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de');

        $this->socialCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
        $this->assertDatabaseMissing('user_social_accounts', ['user_id' => $user->getKey()]);
        $this->assertDatabaseHas('user_pending_social_links', [
            'user_id' => $user->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'google-sub-1',
        ]);
        Mail::assertQueued(SocialLinkConfirmation::class, fn ($mail) => $mail->hasTo('max@firma.de'));
        Mail::assertNotQueued(SocialAccountLinked::class);
    }

    public function test_repeated_attempt_replaces_pending_link(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de'], verified: false);
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de');

        $this->socialCallback();
        $this->socialCallback();

        $this->assertSame(1, DB::table('user_pending_social_links')->where('user_id', $user->getKey())->count());
    }

    public function test_unverified_google_email_is_rejected_with_reason(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de', emailVerified: false);

        $this->socialCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertDatabaseCount('user_social_accounts', 0);
        $this->assertDatabaseCount('user_pending_social_links', 0);
    }

    public function test_domain_outside_allowed_list_is_rejected(): void
    {
        config()->set('userauth.social.allowed_domains', ['firma.de']);
        $this->makeUser(['email' => 'max@gmail.com']);
        $this->provider->returnIdentity('google-sub-1', 'max@gmail.com');

        $this->socialCallback()->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertDatabaseCount('user_social_accounts', 0);
    }

    public function test_domain_restriction_also_applies_to_already_linked_accounts(): void
    {
        config()->set('userauth.social.allowed_domains', ['firma.de']);
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->linkGoogle($user, 'google-sub-1');
        $this->provider->returnIdentity('google-sub-1', 'max@privat.de');

        $this->socialCallback()->assertSessionHasErrors('social');

        $this->assertGuest();
    }

    public function test_domain_inside_allowed_list_is_accepted(): void
    {
        config()->set('userauth.social.allowed_domains', ['firma.de']);
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de', hostedDomain: 'firma.de');

        $this->socialCallback();

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_other_google_account_linked_is_rejected(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->linkGoogle($user, 'google-sub-old');
        $this->provider->returnIdentity('google-sub-new', 'max@firma.de');

        $this->socialCallback()->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertDatabaseMissing('user_social_accounts', ['provider_user_id' => 'google-sub-new']);
    }

    public function test_unknown_email_is_rejected_when_registration_disabled(): void
    {
        $this->provider->returnIdentity('google-sub-1', 'fremd@gmail.com');

        $this->socialCallback()->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'fremd@gmail.com']);
    }

    public function test_unknown_email_creates_account_when_registration_enabled(): void
    {
        config()->set('userauth.social.allow_registration', true);
        $this->provider->returnIdentity('google-sub-1', 'neu@gmail.com', name: 'Neu Nutzer');

        $this->socialCallback()->assertRedirect('/');

        $user = $this->userModel()::query()->where('email', 'neu@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(3, (int) $user->role_id);
        $this->assertSame('Neu Nutzer', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotEmpty($user->password);
        $this->assertFalse(Hash::check('', $user->password));
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $user->getKey(),
            'provider_user_id' => 'google-sub-1',
        ]);
    }

    public function test_soft_deleted_user_with_same_email_is_rejected(): void
    {
        config()->set('userauth.social.allow_registration', true);
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $user->delete();
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de');

        $this->socialCallback()->assertSessionHasErrors('social');

        $this->assertGuest();
        $this->assertSame(1, $this->userModel()::withTrashed()->where('email', 'max@firma.de')->count());
    }

    public function test_linked_but_soft_deleted_user_cannot_log_in(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->linkGoogle($user, 'google-sub-1');
        $user->delete();
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de');

        $this->socialCallback()->assertSessionHasErrors('social');

        $this->assertGuest();
    }

    public function test_provider_failure_redirects_to_login_with_error(): void
    {
        $this->provider->failWithUnavailable();

        $this->socialCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->assertGuest();
    }

    public function test_two_factor_is_required_after_google_login_when_enabled(): void
    {
        config()->set('userauth.two_factor_enabled', true);
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->linkGoogle($user, 'google-sub-1');
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de');

        $this->socialCallback()->assertRedirect(route('verify.index'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->two_factor_code);
        Mail::assertSent(TwoFactorCode::class, fn ($mail) => $mail->hasTo('max@firma.de'));
    }

    public function test_linking_is_written_to_audit_log_when_enabled(): void
    {
        config()->set('userauth.audit_log_enabled', true);
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de');

        $this->socialCallback();

        $this->assertDatabaseHas('user_auth_audit_logs', [
            'user_id' => $user->getKey(),
            'event' => 'social_linked',
        ]);
    }
}
