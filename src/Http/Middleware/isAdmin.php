<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Zugriff\Zugriffspruefung;
use ITHilbert\UserAuth\Http\Responses\ZugriffVerweigert;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route nur für Admin (gültige, nicht gelöschte Rolle mit dem Schlüssel `admin`, unabhängig von der ID).
 */
final class isAdmin
{
    public function __construct(
        private readonly Zugriffspruefung $zugriffspruefung,
        private readonly ZugriffVerweigert $verweigert,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->verweigert->nichtAngemeldet();
        }

        if ($this->zugriffspruefung->istPrivilegiert($user)) {
            return $next($request);
        }

        return $this->verweigert->fehlendesRecht();
    }
}
