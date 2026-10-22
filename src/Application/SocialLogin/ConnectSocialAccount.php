<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialAccountRepository;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialUserRepository;
use ITHilbert\UserAuth\Application\SocialLogin\Exceptions\SocialProviderUnavailableException;
use ITHilbert\UserAuth\Domain\SocialLogin\AllowedDomainPolicy;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\AccountDisabledException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\ProviderAlreadyConnectedException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialAccountTakenException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialLoginException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

/**
 * Anwendungsfall: Ein eingeloggter Nutzer verbindet einen Social-Account (E-Mail darf abweichen).
 */
final class ConnectSocialAccount
{
    public function __construct(
        private readonly SocialIdentityProvider $identityProvider,
        private readonly SocialAccountRepository $accounts,
        private readonly SocialUserRepository $users,
        private readonly AllowedDomainPolicy $domainPolicy,
        private readonly SocialLoginSettings $settings,
        private readonly LinkSocialAccount $linkAccount,
    ) {}

    /**
     * @throws SocialLoginException
     * @throws SocialProviderUnavailableException
     */
    public function handle(int|string $userId, SocialProvider $provider): void
    {
        $identity = $this->identityProvider->fetchIdentity($provider);
        $user = $this->users->findActiveById($userId) ?? throw new AccountDisabledException;

        $this->domainPolicy->assertAllowed($identity, $this->settings->allowedDomains());

        if ($this->accounts->findUserIdByAccount($identity->provider, $identity->providerUserId) !== null) {
            throw new SocialAccountTakenException;
        }

        if ($this->accounts->userHasProvider($user->id, $identity->provider)) {
            throw new ProviderAlreadyConnectedException;
        }

        $this->linkAccount->handle($user, $identity);
    }
}
