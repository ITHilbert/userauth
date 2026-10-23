<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Zugriff\Zugriffspruefung;
use ITHilbert\UserAuth\Http\Responses\ZugriffVerweigert;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route nur mit Rolle: `hasRole:name`. Dev und Admin kommen immer durch (auch bei `hasRole:dev`).
 */
final class hasRole
{
    public function __construct(
        private readonly Zugriffspruefung $zugriffspruefung,
        private readonly ZugriffVerweigert $verweigert,
    ) {}

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->verweigert->nichtAngemeldet();
        }

        if ($this->zugriffspruefung->istPrivilegiert($user) || $this->zugriffspruefung->hatRolle($user, $role)) {
            return $next($request);
        }

        return $this->verweigert->fehlendesRecht();
    }
}
