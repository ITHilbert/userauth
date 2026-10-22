<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

final readonly class LocalUser
{
    public function __construct(
        public int|string $id,
        public string $email,
        public ?string $name,
        public bool $emailVerified,
    ) {}
}
