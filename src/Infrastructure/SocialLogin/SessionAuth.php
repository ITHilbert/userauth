<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\SocialLogin;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\AuthSession;

final class SessionAuth implements AuthSession
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Request $request,
    ) {}

    public function login(int|string $userId): Authenticatable
    {
        $guard = $this->auth->guard();

        if (! $guard instanceof StatefulGuard) {
            throw new SocialLoginMisconfiguredException('The default auth guard must be stateful.');
        }

        $user = $guard->loginUsingId($userId);

        if ($user === false) {
            throw new SocialLoginMisconfiguredException('User could not be logged in.');
        }

        $this->request->session()->regenerate();

        return $user;
    }
}
