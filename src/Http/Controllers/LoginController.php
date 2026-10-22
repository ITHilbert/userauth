<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ITHilbert\UserAuth\Application\Auth\AuthenticationNextStep;
use ITHilbert\UserAuth\Application\Auth\CompleteAuthentication;
use ITHilbert\UserAuth\Traits\Auth\AuthenticatesUsers;

final class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @return string
     */
    public function redirectPath()
    {
        return config('userauth.redirect_after_login', '/home');
    }

    /**
     * The user has logged out of the application.
     *
     * @return mixed
     */
    protected function loggedOut(Request $request)
    {
        return redirect(config('userauth.redirect_after_logout', '/home'));
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(private readonly CompleteAuthentication $completeAuthentication)
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view('userauth::login');
    }

    protected function authenticated(Request $request, $user)
    {
        if ($this->completeAuthentication->handle($user) === AuthenticationNextStep::TwoFactorRequired) {
            return redirect()->route('verify.index');
        }

        return redirect()->intended($this->redirectPath());
    }
}
