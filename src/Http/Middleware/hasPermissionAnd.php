<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class hasPermissionAnd
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
            if (! $user->hasPermission($permission)) {
                if (config('userauth.redirect_on_no_permission', 'login') === 'login') {
                    return redirect()->guest(route('login'));
                }

                return redirect()->route('no-permission', [$request, $user->id]);
            }
        }

        return $next($request);
    }
}
