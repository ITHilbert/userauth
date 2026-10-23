<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

final class isAdmin
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        /** @var Authenticatable|null $user */
        $user = Auth::user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if ($user->role_id == 1 || $user->role_id == 2) {
            return $next($request);
        }

        if (config('userauth.redirect_on_no_permission', 'login') === 'login') {
            return redirect()->guest(route('login'));
        }

        return Redirect::route('no-permission');
    }
}
