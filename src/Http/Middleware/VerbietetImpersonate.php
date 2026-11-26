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
        abort_if($request->session()->has('impersonated_by'), 403, (string) __('userauth::passkey.impersonate_gesperrt'));

        return $next($request);
    }
}
