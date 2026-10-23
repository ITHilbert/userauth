<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use Illuminate\Support\Str;
use ITHilbert\UserAuth\Application\Auth\CompleteAuthentication;
use ITHilbert\UserAuth\Application\Auth\Contracts\Sitzungsanmeldung;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialAccountRepository;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialUserRepository;
use ITHilbert\UserAuth\Application\SocialLogin\Exceptions\SocialProviderUnavailableException;
use ITHilbert\UserAuth\Domain\SocialLogin\AllowedDomainPolicy;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\AccountDisabledException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\EmailNotVerifiedException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\ProviderAlreadyConnectedException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\RegistrationDisabledException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialLoginException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

/**
 * Anwendungsfall: Anmeldung eines Gastes über einen Social-Anbieter.
 */
final class HandleSocialLogin
{
    public function __construct(
        private readonly SocialIdentityProvider $identityProvider,
        private readonly SocialAccountRepository $accounts,
        private readonly SocialUserRepository $users,
        private readonly AllowedDomainPolicy $domainPolicy,
        private readonly SocialLoginSettings $settings,
        private readonly LinkSocialAccount $linkAccount,
        private readonly IssueSocialLinkConfirmation $issueConfirmation,
        private readonly Sitzungsanmeldung $sitzung,
        private readonly CompleteAuthentication $completeAuthentication,
    ) {}

    /**
     * @throws SocialLoginException
     * @throws SocialProviderUnavailableException
     */
    public function handle(SocialProvider $provider): SocialLoginResult
    {
        $identity = $this->identityProvider->fetchIdentity($provider);

        $this->domainPolicy->assertAllowed($identity, $this->settings->allowedDomains());

        if (! $identity->emailVerified) {
            throw new EmailNotVerifiedException;
        }

        $linkedUserId = $this->accounts->findUserIdByAccount($identity->provider, $identity->providerUserId);

        if ($linkedUserId !== null) {
            $user = $this->users->findActiveById($linkedUserId) ?? throw new AccountDisabledException;

            return $this->login($user);
        }

        $user = $this->users->findActiveByEmail($identity->email);

        return $user === null ? $this->registerOrReject($identity) : $this->linkExisting($user, $identity);
    }

    private function linkExisting(LocalUser $user, SocialIdentity $identity): SocialLoginResult
    {
        if ($this->accounts->userHasProvider($user->id, $identity->provider)) {
            throw new ProviderAlreadyConnectedException;
        }

        if (! $user->emailVerified) {
            $this->issueConfirmation->handle($user, $identity);

            return SocialLoginResult::confirmationRequired();
        }

        $this->linkAccount->handle($user, $identity);

        return $this->login($user);
    }

    private function registerOrReject(SocialIdentity $identity): SocialLoginResult
    {
        if ($this->users->existsDeletedWithEmail($identity->email)) {
            throw new AccountDisabledException;
        }

        if (! $this->settings->allowRegistration()) {
            throw new RegistrationDisabledException;
        }

        $user = $this->users->createVerified(
            $identity->name ?? Str::before($identity->email, '@'),
            $identity->email,
            $this->settings->defaultRole(),
        );
        $this->linkAccount->handle($user, $identity);

        return $this->login($user);
    }

    /**
     * Angemeldet wird in CompleteAuthentication – bei aktiver 2FA erst nach dem Code (AK14).
     *
     * @throws AccountDisabledException
     */
    private function login(LocalUser $user): SocialLoginResult
    {
        $authenticatable = $this->sitzung->nutzer($user->id) ?? throw new AccountDisabledException;

        return SocialLoginResult::loggedIn($this->completeAuthentication->handle($authenticatable));
    }
}
