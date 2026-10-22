<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Tests\Fakes\FakeSocialIdentityProvider;
use Tests\TestCase;

abstract class SocialLoginTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeSocialIdentityProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('userauth.social.enabled', true);
        config()->set('userauth.social.providers', ['google']);
        config()->set('userauth.social.allow_registration', false);
        config()->set('userauth.social.default_role', 'user');
        config()->set('userauth.social.allowed_domains', []);
        config()->set('userauth.social.pending_link_ttl_minutes', 60);
        config()->set('userauth.two_factor_enabled', false);
        config()->set('userauth.audit_log_enabled', false);
        config()->set('userauth.redirect_after_login', '/');

        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Entwickler', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Administrator', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'role' => 'user', 'role_display' => 'Benutzer', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->provider = new FakeSocialIdentityProvider;
        $this->app->instance(SocialIdentityProvider::class, $this->provider);

        Mail::fake();
    }

    /**
     * @return class-string<Model>
     */
    protected function userModel(): string
    {
        return config('auth.providers.users.model');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(array $attributes = [], bool $verified = true): Model
    {
        $factory = $this->userModel()::factory();

        if (! $verified) {
            $factory = $factory->unverified();
        }

        return $factory->create(array_merge(['role_id' => 3], $attributes));
    }

    protected function linkGoogle(Model $user, string $sub): void
    {
        DB::table('user_social_accounts')->insert([
            'user_id' => $user->getKey(),
            'provider' => 'google',
            'provider_user_id' => $sub,
            'email' => $user->email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function socialCallback(): TestResponse
    {
        return $this->get(route('userauth.social.callback', 'google'));
    }
}
