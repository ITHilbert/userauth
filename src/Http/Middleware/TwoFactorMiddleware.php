<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Auth\Abmelden;
use Symfony\Component\HttpFoundation\Response;

/**
 * Übergang zur sitzungsgebundenen 2FA (Randfall der Spec userauth-haertung): Vor dem Update war ein Nutzer nach dem
 * Passwort bereits angemeldet, „2FA offen“ stand in `users.two_factor_code`. Solche Sitzungen sind keine gültige
 * Anmeldung mehr – sie werden abgemeldet, der Nutzer meldet sich neu an.
 *
 * Erkennung: Jede Anmeldung seit dem Update trägt die Markierung MARKIERUNG (Listener auf `Login`); fehlt sie und
 * steht beim Nutzer noch ein alter Code, stammt die Sitzung aus der Zeit davor. Die Spalten werden nicht mehr
 * beschrieben. Die neue 2FA selbst braucht diese Middleware nicht: Vor dem Code ist niemand angemeldet.
 */
final class TwoFactorMiddleware
{
    public const MARKIERUNG = 'userauth.anmeldung_sitzungsgebunden';

    public function __construct(private readonly Abmelden $abmelden) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('userauth.two_factor_enabled')
            || ! $request->hasSession()
            || $request->session()->get(self::MARKIERUNG) === true
            || ! $this->hatAltenOffenenCode($request->user())) {
            return $next($request);
        }

        $this->abmelden->handle();

        return redirect()->route('login')->withErrors(['two_factor_code' => __('userauth::zwei_faktor.neu_anmelden')]);
    }

    private function hatAltenOffenenCode(mixed $nutzer): bool
    {
        return $nutzer instanceof Model && ($nutzer->getAttributes()['two_factor_code'] ?? null) !== null;
    }
}
