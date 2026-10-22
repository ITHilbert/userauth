<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\SocialLogin;

enum SocialProvider: string
{
    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
        };
    }
}
