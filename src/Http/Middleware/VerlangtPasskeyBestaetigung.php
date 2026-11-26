<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Passkey\PasskeyBestaetigung;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lehnt „Passkey hinzufügen“ ohne gültige Bestätigung mit 403 ab (passkey-bestaetigung, AK1, AK3), bevor der
 * Controller eine Challenge ablegt oder einen Passkey speichert. Das Skript erkennt die Antwort an
 * `bestaetigung_erforderlich`.
 */
final class VerlangtPasskeyBestaetigung
{
    public function __construct(private readonly PasskeyBestaetigung $bestaetigung) {}

    public function handle(Request $request, Closure $next): Response
    {
        $nutzer = $request->user();

        if ($nutzer === null || ! $this->bestaetigung->gueltig($nutzer->getAuthIdentifier())) {
            return response()->json([
                'message' => __('userauth::passkey.fehler.bestaetigung_erforderlich'),
                'bestaetigung_erforderlich' => true,
            ], 403);
        }

        return $next($request);
    }
}
