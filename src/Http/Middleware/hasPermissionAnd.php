<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Zugriff\Zugriffspruefung;
use ITHilbert\UserAuth\Http\Responses\ZugriffVerweigert;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route nur mit ALLEN Rechten: `hasPermissionAnd:a,b` (Dev/Admin: immer).
 */
final class hasPermissionAnd
{
    public function __construct(
        private readonly Zugriffspruefung $zugriffspruefung,
        private readonly ZugriffVerweigert $verweigert,
    ) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->verweigert->nichtAngemeldet();
        }

        if ($this->zugriffspruefung->hatAlleRechte($user, ...$permissions)) {
            return $next($request);
        }

        return $this->verweigert->fehlendesRecht();
    }
}
