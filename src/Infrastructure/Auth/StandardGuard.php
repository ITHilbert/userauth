<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Standard-Guard des Hosts; die Anmeldung des Pakets braucht einen sitzungsbasierten Guard.
 */
final class StandardGuard
{
    public function __construct(private readonly AuthFactory $auth) {}

    /**
     * @throws AuthGuardMisconfiguredException
     */
    public function sessionGuard(): SessionGuard
    {
        $guard = $this->auth->guard();

        if (! $guard instanceof SessionGuard) {
            throw new AuthGuardMisconfiguredException('The default auth guard must be a session guard (driver "session").');
        }

        return $guard;
    }
}
