<?php

namespace ITHilbert\UserAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class isDev
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
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return redirect()->guest(route('login'));
        }

        if($user->role_id == 1){
            return $next($request);
        }

        if (config('userauth.redirect_on_no_permission', 'login') === 'login') {
            return redirect()->guest(route('login'));
        }

        return redirect()->route('no-permission', [$request, $user->id]);
    }
}
