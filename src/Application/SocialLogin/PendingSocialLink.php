<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

final readonly class PendingSocialLink
{
    public function __construct(
        public int|string $userId,
        public SocialProvider $provider,
        public string $providerUserId,
        public string $tokenHash,
    ) {}
}
