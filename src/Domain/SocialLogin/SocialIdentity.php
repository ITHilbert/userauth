<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\SocialLogin;

final readonly class SocialIdentity
{
    public function __construct(
        public SocialProvider $provider,
        public string $providerUserId,
        public string $email,
        public bool $emailVerified,
        public ?string $name,
        public ?string $hostedDomain,
    ) {}

    public function emailDomain(): string
    {
        return strtolower((string) substr(strrchr($this->email, '@') ?: '', 1));
    }
}
