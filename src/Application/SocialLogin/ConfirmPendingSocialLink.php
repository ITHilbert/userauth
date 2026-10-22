<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\PendingSocialLinkRepository;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialAccountRepository;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialUserRepository;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\AccountDisabledException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\InvalidLinkTokenException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\ProviderAlreadyConnectedException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialAccountTakenException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialLoginException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;

/**
 * Anwendungsfall: Bestätigungslink (POST) -> Verknüpfung + E-Mail als bestätigt markieren. Kein Login.
 */
final class ConfirmPendingSocialLink
{
    public function __construct(
        private readonly PendingSocialLinkRepository $pendingLinks,
        private readonly SocialAccountRepository $accounts,
        private readonly SocialUserRepository $users,
        private readonly LinkSocialAccount $linkAccount,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * Prüft nur, ob der Token gültig ist (GET-Bestätigungsseite; verknüpft nichts).
     *
     * @throws InvalidLinkTokenException
     */
    public function preview(string $token): PendingSocialLink
    {
        return $this->pendingLinks->findValid(hash('sha256', $token), CarbonImmutable::now())
            ?? throw new InvalidLinkTokenException;
    }

    /**
     * @throws SocialLoginException
     */
    public function handle(string $token): void
    {
        $tokenHash = hash('sha256', $token);

        $this->db->transaction(function () use ($tokenHash): void {
            $pending = $this->pendingLinks->findValidForUpdate($tokenHash, CarbonImmutable::now())
                ?? throw new InvalidLinkTokenException;

            $user = $this->users->findActiveById($pending->userId) ?? throw new AccountDisabledException;

            if ($this->accounts->findUserIdByAccount($pending->provider, $pending->providerUserId) !== null) {
                throw new SocialAccountTakenException;
            }

            if ($this->accounts->userHasProvider($user->id, $pending->provider)) {
                throw new ProviderAlreadyConnectedException;
            }

            $this->users->markEmailVerified($user->id);
            $this->linkAccount->handle($user, new SocialIdentity(
                provider: $pending->provider,
                providerUserId: $pending->providerUserId,
                email: $user->email,
                emailVerified: true,
                name: $user->name,
                hostedDomain: null,
            ));
            $this->pendingLinks->delete($pending->tokenHash);
        });
    }
}
