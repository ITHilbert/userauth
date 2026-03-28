<?php

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EnforcePasswordPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Wenn nicht eingeloggt oder Feature deaktiviert ist, durchwinken
        if (!Auth::check() || !config('userauth.password_policy.enabled', false)) {
            return $next($request);
        }

        // Nicht auf Routen greifen, die zum Ändern des Passworts da sind
        // Ansonsten Endlosschleife
        $route = $request->route() ? $request->route()->getName() : '';
        if (in_array($route, ['password.edit', 'password.update', 'logout'])) {
            return $next($request);
        }

        $user = Auth::user();
        $daysLimit = config('userauth.password_policy.require_change_every_days', 90);

        if ($daysLimit > 0) {
            // Wenn der User kein changed_at hat, setzen wir simulativ sein Registrierungsdatum oder yesterday
            $lastChanged = $user->password_changed_at ? Carbon::parse($user->password_changed_at) : ($user->created_at ?? now()->subDays($daysLimit + 1));
            
            if ($lastChanged->diffInDays(now()) >= $daysLimit) {
                // Passwort ist abgelaufen - User zwingen
                session()->flash('warning', 'Dein Passwort ist abgelaufen und muss aus Sicherheitsgründen neu gesetzt werden.');
                return redirect()->route('password.edit');
            }
        }

        return $next($request);
    }
}
