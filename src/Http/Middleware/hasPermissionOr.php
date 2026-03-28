<?php

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Redirect;

class hasPermissionOr
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permissions)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return redirect()->guest(route('login'));
        }
        //Admin und Developer  haben immer das Recht
        if ($user->role_id <= 2) {
            return $next($request);
        }
        //recht prüfen
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }
        if (config('userauth.redirect_on_no_permission', 'login') === 'login') {
            return redirect()->guest(route('login'));
        }

        return Redirect::route('no-permission');

    }
}
