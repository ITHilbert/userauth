<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Zugriff\Zugriffspruefung;
use ITHilbert\UserAuth\Http\Responses\ZugriffVerweigert;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route nur mit Recht: `hasPermission:recht` (Dev/Admin: immer).
 */
final class hasPermission
{
    public function __construct(
        private readonly Zugriffspruefung $zugriffspruefung,
        private readonly ZugriffVerweigert $verweigert,
    ) {}

    public function handle(Request $request, Closure $next, string $permission = ''): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->verweigert->nichtAngemeldet();
        }

        if ($this->zugriffspruefung->hatRecht($user, $permission)) {
            return $next($request);
        }

        return $this->verweigert->fehlendesRecht();
    }
}
