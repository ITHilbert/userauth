<?php

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

use Illuminate\Support\Facades\Redirect;

class hasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permission)
    {
        /** @var \App\Models\User $user */
        $user = User::find(Auth::id());
        //Admin und Developer  haben immer das Recht
        if ($user->role_id <= 2) {
            return $next($request);
        }
        //recht prüfen
        if ($user->hasPermission($permission)) {
            return $next($request);
        }

        return Redirect::route('no-permission');
    }
}
