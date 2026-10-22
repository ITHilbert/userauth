<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\SocialLogin;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Application\SocialLogin\Exceptions\SocialProviderUnavailableException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Psr\Log\LoggerInterface;

final class SocialiteIdentityProvider implements SocialIdentityProvider
{
    public function __construct(
        private readonly SocialiteManager $socialite,
        private readonly UrlGenerator $urls,
        private readonly LoggerInterface $logger,
        private readonly Repository $config,
    ) {}

    public function isAvailable(SocialProvider $provider): bool
    {
        $missing = $this->missingKeys($provider);

        if ($missing === []) {
            return true;
        }

        $this->logger->error('Social login aktiviert, aber Anbieter nicht konfiguriert.', [
            'provider' => $provider->value,
            'missing_keys' => $missing,
        ]);

        return false;
    }

    public function authorizationUrl(SocialProvider $provider): string
    {
        try {
            return $this->driver($provider)->redirect()->getTargetUrl();
        } catch (SocialProviderUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->translate($provider, $e);
        }
    }

    public function fetchIdentity(SocialProvider $provider): SocialIdentity
    {
        try {
            $user = $this->driver($provider)->user();
        } catch (SocialProviderUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->translate($provider, $e);
        }

        $providerUserId = (string) $user->getId();
        $email = (string) $user->getEmail();

        if ($providerUserId === '' || $email === '') {
            throw new SocialProviderUnavailableException;
        }

        $raw = (array) ($user->user ?? []);

        return new SocialIdentity(
            provider: $provider,
            providerUserId: $providerUserId,
            email: $email,
            emailVerified: filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            name: $user->getName() ?: null,
            hostedDomain: isset($raw['hd']) && is_string($raw['hd']) && $raw['hd'] !== '' ? $raw['hd'] : null,
        );
    }

    /**
     * @throws SocialProviderUnavailableException
     */
    private function driver(SocialProvider $provider): AbstractProvider
    {
        if (! $this->isAvailable($provider)) {
            throw new SocialProviderUnavailableException;
        }

        $credentials = (array) $this->config->get('services.'.$provider->value, []);

        $driver = $this->socialite->buildProvider($this->driverClass($provider), [
            'client_id' => (string) $credentials['client_id'],
            'client_secret' => (string) $credentials['client_secret'],
            'redirect' => $this->urls->route('userauth.social.callback', $provider->value),
        ]);

        return $driver;
    }

    /**
     * @return class-string<AbstractProvider>
     */
    private function driverClass(SocialProvider $provider): string
    {
        return match ($provider) {
            SocialProvider::Google => GoogleProvider::class,
        };
    }

    /**
     * @return list<string>
     */
    private function missingKeys(SocialProvider $provider): array
    {
        $credentials = (array) $this->config->get('services.'.$provider->value, []);

        return array_values(array_filter(
            ['client_id', 'client_secret'],
            static fn (string $key): bool => ! isset($credentials[$key]) || ! is_string($credentials[$key]) || trim($credentials[$key]) === '',
        ));
    }

    private function translate(SocialProvider $provider, \Throwable $e): SocialProviderUnavailableException
    {
        $this->logger->warning('Social login: provider request failed.', [
            'provider' => $provider->value,
            'exception' => $e::class,
        ]);

        return new SocialProviderUnavailableException(previous: $e);
    }
}
