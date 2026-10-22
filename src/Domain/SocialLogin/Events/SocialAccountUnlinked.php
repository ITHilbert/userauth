<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\SocialLogin\Events;

use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

final readonly class SocialAccountUnlinked
{
    public function __construct(
        public int|string $userId,
        public string $userEmail,
        public SocialProvider $provider,
    ) {}
}
