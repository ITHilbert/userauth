<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Fixtures;

/**
 * User-Model wie im Laravel-Standard ab 11: `password` ist als `hashed` gecastet.
 */
class UserMitHashCast extends User
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
