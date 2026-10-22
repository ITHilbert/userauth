<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

final class SocialLoginRoutesTest extends SocialLoginTestCase
{
    public function test_redirect_route_sends_user_to_provider(): void
    {
        $this->get(route('userauth.social.redirect', 'google'))
            ->assertRedirect('https://accounts.example.test/oauth?provider=google');
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->get('/auth/facebook/redirect')->assertNotFound();
        $this->get('/auth/facebook/callback')->assertNotFound();
    }

    public function test_provider_not_enabled_in_config_returns_404(): void
    {
        config()->set('userauth.social.providers', []);

        $this->get('/auth/google/redirect')->assertNotFound();
    }

    public function test_all_social_routes_return_404_when_feature_disabled(): void
    {
        config()->set('userauth.social.enabled', false);

        $this->get('/auth/google/redirect')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();
        $this->get('/auth/social/confirm/abc')->assertNotFound();
    }

    public function test_tailwind_login_page_shows_google_button_when_enabled(): void
    {
        config()->set('userauth.theme', 'tailwind');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('userauth.social.redirect', 'google'), false);
    }

    public function test_bootstrap_login_page_shows_google_button_when_enabled(): void
    {
        config()->set('userauth.theme', 'bootstrap');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('userauth.social.redirect', 'google'), false);
    }

    public function test_login_page_hides_google_button_when_disabled(): void
    {
        config()->set('userauth.theme', 'tailwind');
        config()->set('userauth.social.enabled', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('/auth/google/redirect', false);
    }

    public function test_login_page_loads_no_external_google_assets(): void
    {
        config()->set('userauth.theme', 'tailwind');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('gstatic.com', false)
            ->assertDontSee('fonts.googleapis.com', false)
            ->assertDontSee('accounts.google.com/gsi', false);
    }

    public function test_accounts_page_requires_authentication(): void
    {
        $this->get(route('userauth.social.accounts'))->assertRedirect(route('login'));
    }

    public function test_accounts_page_renders_for_authenticated_user(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('userauth.social.accounts'))
            ->assertOk()
            ->assertSee('Google');
    }
}
