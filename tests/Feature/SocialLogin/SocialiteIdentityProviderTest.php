<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

use Illuminate\Support\Facades\Log;
use ITHilbert\UserAuth\Application\SocialLogin\Exceptions\SocialProviderUnavailableException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;
use ITHilbert\UserAuth\Infrastructure\SocialLogin\SocialiteIdentityProvider;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Adapter-Test gegen den ECHTEN Socialite-Google-Treiber (ohne HTTP-Aufruf).
 */
final class SocialiteIdentityProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('userauth.social.enabled', true);
        config()->set('userauth.social.providers', ['google']);
        config()->set('services.google', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret-XYZ',
        ]);

        $request = $this->app['request'];
        $request->setLaravelSession($this->app['session.store']);
    }

    private function adapter(): SocialiteIdentityProvider
    {
        return $this->app->make(SocialiteIdentityProvider::class);
    }

    public function test_authorization_url_contains_client_id_and_package_callback_without_services_redirect_key(): void
    {
        $url = $this->adapter()->authorizationUrl(SocialProvider::Google);

        $this->assertStringStartsWith('https://accounts.google.com/', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('test-client-id', $query['client_id'] ?? null);
        $this->assertSame(route('userauth.social.callback', 'google'), $query['redirect_uri'] ?? null);
        $this->assertStringContainsString('email', (string) ($query['scope'] ?? ''));
        $this->assertArrayHasKey('state', $query);
    }

    public function test_is_available_when_credentials_are_configured(): void
    {
        $this->assertTrue($this->adapter()->isAvailable(SocialProvider::Google));
    }

    public function test_is_not_available_and_logs_error_when_client_id_missing(): void
    {
        config()->set('services.google.client_id', null);
        Log::spy();

        $this->assertFalse($this->adapter()->isAvailable(SocialProvider::Google));

        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_is_not_available_when_client_secret_missing(): void
    {
        config()->set('services.google.client_secret', '');
        Log::spy();

        $this->assertFalse($this->adapter()->isAvailable(SocialProvider::Google));
    }

    public function test_authorization_url_throws_project_exception_when_not_configured(): void
    {
        config()->set('services.google', []);
        Log::spy();

        $this->expectException(SocialProviderUnavailableException::class);

        $this->adapter()->authorizationUrl(SocialProvider::Google);
    }

    public function test_fetch_identity_throws_project_exception_when_not_configured(): void
    {
        config()->set('services.google', []);
        Log::spy();

        $this->expectException(SocialProviderUnavailableException::class);

        $this->adapter()->fetchIdentity(SocialProvider::Google);
    }

    public function test_logs_never_contain_client_secret(): void
    {
        config()->set('services.google.client_id', '');
        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message.' '.json_encode($event->context);
        });

        $this->adapter()->isAvailable(SocialProvider::Google);

        foreach ($logged as $line) {
            $this->assertStringNotContainsString('test-client-secret-XYZ', $line);
        }
    }
}
