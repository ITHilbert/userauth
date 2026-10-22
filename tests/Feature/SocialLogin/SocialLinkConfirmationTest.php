<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use ITHilbert\UserAuth\Mail\SocialAccountLinked;
use ITHilbert\UserAuth\Mail\SocialLinkConfirmation;

final class SocialLinkConfirmationTest extends SocialLoginTestCase
{
    private Model $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->makeUser(['email' => 'max@firma.de'], verified: false);
    }

    private function requestConfirmationToken(string $sub = 'google-sub-1'): string
    {
        $this->provider->returnIdentity($sub, 'max@firma.de');
        $this->socialCallback();

        $url = null;
        Mail::assertQueued(SocialLinkConfirmation::class, function (SocialLinkConfirmation $mail) use (&$url): bool {
            $url = $mail->confirmUrl;

            return true;
        });

        $this->assertIsString($url);

        return basename((string) parse_url($url, PHP_URL_PATH));
    }

    public function test_confirmation_url_points_to_confirm_route(): void
    {
        $token = $this->requestConfirmationToken();

        $this->assertNotSame('', $token);
        $this->get(route('userauth.social.confirm', $token))->assertOk();
    }

    public function test_opening_link_does_not_link_yet(): void
    {
        $token = $this->requestConfirmationToken();

        $this->get(route('userauth.social.confirm', $token))->assertOk();

        $this->assertDatabaseCount('user_social_accounts', 0);
        $this->assertDatabaseCount('user_pending_social_links', 1);
    }

    public function test_token_is_stored_only_as_hash(): void
    {
        $token = $this->requestConfirmationToken();

        $this->assertDatabaseMissing('user_pending_social_links', ['token_hash' => $token]);
        $this->assertDatabaseHas('user_pending_social_links', ['token_hash' => hash('sha256', $token)]);
    }

    public function test_confirming_links_account_verifies_email_and_does_not_log_in(): void
    {
        $token = $this->requestConfirmationToken();

        $this->post(route('userauth.social.confirm.store', $token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertGuest();
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $this->user->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'google-sub-1',
        ]);
        $this->assertDatabaseCount('user_pending_social_links', 0);
        $this->assertNotNull($this->user->fresh()->email_verified_at);
        Mail::assertQueued(SocialAccountLinked::class, fn ($mail) => $mail->hasTo('max@firma.de'));
    }

    public function test_google_login_works_after_confirmation(): void
    {
        $token = $this->requestConfirmationToken();
        $this->post(route('userauth.social.confirm.store', $token));

        $this->socialCallback()->assertRedirect('/');

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_token_can_only_be_used_once(): void
    {
        $token = $this->requestConfirmationToken();
        $this->post(route('userauth.social.confirm.store', $token));

        $this->post(route('userauth.social.confirm.store', $token))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = $this->requestConfirmationToken();
        $this->travel(61)->minutes();

        $this->post(route('userauth.social.confirm.store', $token))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->assertDatabaseCount('user_social_accounts', 0);
        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_invalid_token_is_rejected_on_show_and_store(): void
    {
        $this->get(route('userauth.social.confirm', 'ungueltig'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');

        $this->post(route('userauth.social.confirm.store', 'ungueltig'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('social');
    }

    public function test_confirmation_fails_if_google_account_was_linked_to_other_user_meanwhile(): void
    {
        $token = $this->requestConfirmationToken();
        $other = $this->makeUser(['email' => 'andere@firma.de']);
        $this->linkGoogle($other, 'google-sub-1');

        $this->post(route('userauth.social.confirm.store', $token))
            ->assertSessionHasErrors('social');

        $this->assertDatabaseMissing('user_social_accounts', ['user_id' => $this->user->getKey()]);
        $this->assertSame(1, DB::table('user_social_accounts')->count());
    }
}
