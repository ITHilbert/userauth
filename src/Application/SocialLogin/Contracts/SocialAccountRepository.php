<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin\Contracts;

use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialAccountTakenException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

interface SocialAccountRepository
{
    public function findUserIdByAccount(SocialProvider $provider, string $providerUserId): int|string|null;

    public function userHasProvider(int|string $userId, SocialProvider $provider): bool;

    /**
     * @throws SocialAccountTakenException
     */
    public function link(int|string $userId, SocialIdentity $identity): void;

    public function unlink(int|string $userId, SocialProvider $provider): bool;

    /**
     * @return array<string, string|null> Provider-Key => bei dem Anbieter hinterlegte E-Mail
     */
    public function providersOfUser(int|string $userId): array;
}
