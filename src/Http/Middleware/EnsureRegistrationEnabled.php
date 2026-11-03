<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Registrierung\RegistrierungsEinstellungen;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert 404, solange `userauth.registration.enabled` aus ist – auch für Bestätigungslinks, die noch
 * unterwegs sind. Prüfung zur Request-Zeit: Die Routen sind immer registriert, Ein- und Ausschalten wirkt
 * ohne neuen Routen-Cache. Läuft vor der CSRF-Prüfung (MiddlewarePrioritaet), damit auch POST ohne Token 404 bekommt.
 */
final class EnsureRegistrationEnabled
{
    public function __construct(private readonly RegistrierungsEinstellungen $einstellungen) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->einstellungen->aktiv(), 404);

        return $next($request);
    }
}
