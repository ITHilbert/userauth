<?php

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class hasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $role)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return redirect()->guest(route('login'));
        }
        //Admin und Developer  haben immer das Recht
        if($user->role_id <= 2){
            return $next($request);
        }
        //recht prüfen
        if($user->hasRole($role)){
            return $next($request);
        }

        if (config('userauth.redirect_on_no_permission', 'login') === 'login') {
            return redirect()->guest(route('login'));
        }

        return redirect()->route('no-permission', [$request, $user->id]);
    }
}
