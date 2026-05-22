<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class isDev
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if ($user->role_id == 1) {
            return $next($request);
        }

        if (config('userauth.redirect_on_no_permission', 'login') === 'login') {
            return redirect()->guest(route('login'));
        }

        return redirect()->route('no-permission', [$request, $user->id]);
    }
}
