<?php

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class TwoFactorMiddleware
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();

        if (auth()->check() && config('userauth.two_factor_enabled') && $user->two_factor_code) {
            if ($user->two_factor_expires_at && $user->two_factor_expires_at->lt(now())) {
                $user->two_factor_code = null;
                $user->two_factor_expires_at = null;
                $user->save();

                auth()->logout();
                return redirect()->route('login')->withErrors(['email' => 'Ihr Sicherheitscode ist abgelaufen. Bitte loggen Sie sich erneut ein.']);
            }

            if (!$request->is('verify*')) {
                return redirect()->route('verify.index');
            }
        }

        return $next($request);
    }
}
