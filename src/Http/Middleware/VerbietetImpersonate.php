<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lehnt Anfragen innerhalb einer Impersonate-Sitzung mit 403 ab (AK22), bevor Controller oder Challenge-Ablage greifen.
 */
final class VerbietetImpersonate
{
    public function handle(Request $request, Closure $next): Response
    {
        self::abbrechenUnterImpersonate($request);

        return $next($request);
    }

    /**
     * 403 mit `userauth::passkey.impersonate_gesperrt`, wenn die Sitzung eine Übernahme ist; für Prüfungen, die nur
     * in einem Teil einer Route gelten (Social-Callback beim Verknüpfen, AK10 von passkey-bestaetigung).
     */
    public static function abbrechenUnterImpersonate(Request $request): void
    {
        abort_if($request->hasSession() && $request->session()->has('impersonated_by'), 403, (string) __('userauth::passkey.impersonate_gesperrt'));
    }
}
