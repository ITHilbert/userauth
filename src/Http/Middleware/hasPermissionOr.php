<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

final class hasPermissionOr
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permissions)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }
        // Admin und Developer  haben immer das Recht
        if ($user->role_id <= 2) {
            return $next($request);
        }
        // recht prüfen
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
