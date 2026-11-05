<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Übergangs-Alias für Hosts mit `isDev` in ihren Routen: wirkt genau wie `isAdmin`
 * (Entscheidung 0011). Eine eigene Dev-Prüfung gibt es nicht mehr.
 */
final class isDev
{
    public function __construct(private readonly isAdmin $isAdmin) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->isAdmin->handle($request, $next);
    }
}
