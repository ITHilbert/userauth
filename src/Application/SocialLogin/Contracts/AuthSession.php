<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AuthSession
{
    /**
     * Meldet den Nutzer an und erneuert die Session-ID.
     */
    public function login(int|string $userId): Authenticatable;
}
