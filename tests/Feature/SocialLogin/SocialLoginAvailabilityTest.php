<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

/**
 * Technische Fehler und fehlende Konfiguration dürfen nie als Exception-/Debug-Seite beim User landen.
 */
final class SocialLoginAvailabilityTest extends SocialLoginTestCase
{
    private function userMessage(): string
    {
        return (string) __('userauth::social.errors.provider_unavailable');
    }

    public function test_redirect_failure_returns_to_login_with_friendly_message(): void
    {
        $this->provider->failOnRedirect();

        $this->get(route('userauth.social.redirect', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['social' => $this->userMessage()]);
    }

    public function test_callback_failure_shows_friendly_message_not_technical_details(): void
    {
        $this->provider->failWithUnavailable();

        $this->socialCallback()
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['social' => $this->userMessage()]);
    }

    public function test_login_page_hides_google_button_when_provider_not_configured(): void
    {
        $this->provider->markNotConfigured();

        foreach (['tailwind', 'bootstrap'] as $theme) {
            config()->set('userauth.theme', $theme);

            $this->get(route('login'))
                ->assertOk()
                ->assertDontSee(route('userauth.social.redirect', 'google'), false);
        }
    }

    public function test_direct_redirect_call_when_not_configured_shows_friendly_message(): void
    {
        $this->provider->markNotConfigured();

        $this->get(route('userauth.social.redirect', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['social' => $this->userMessage()]);
    }

    public function test_accounts_page_hides_connect_link_when_not_configured(): void
    {
        $this->provider->markNotConfigured();
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('userauth.social.accounts'))
            ->assertOk()
            ->assertDontSee(route('userauth.social.redirect', 'google'), false);
    }

    public function test_redirect_failure_while_logged_in_returns_to_accounts_page(): void
    {
        $this->provider->failOnRedirect();
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('userauth.social.redirect', 'google'))
            ->assertRedirect(route('userauth.social.accounts'))
            ->assertSessionHasErrors(['social' => $this->userMessage()]);
    }
}
