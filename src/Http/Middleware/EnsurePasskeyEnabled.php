<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Passkey\PasskeyEinstellungen;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert 404, solange `userauth.passkey.enabled` aus ist. Prüfung zur Request-Zeit: Die Routen sind immer
 * registriert, Ein- und Ausschalten wirkt ohne neuen Routen-Cache.
 */
final class EnsurePasskeyEnabled
{
    public function __construct(private readonly PasskeyEinstellungen $einstellungen) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->einstellungen->enabled(), 404);

        return $next($request);
    }
}
