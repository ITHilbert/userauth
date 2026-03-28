<?php

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use ITHilbert\UserAuth\Traits\Auth\AuthenticatesUsers;

class LoginController extends Controller
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
     * @param  \Illuminate\Http\Request  $request
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
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view('userauth::login');
    }

    protected function authenticated(Request $request, $user)
    {
        if (config('userauth.two_factor_enabled')) {
            $user->generateTwoFactorCode();

            // Mail senden
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \ITHilbert\UserAuth\Mail\TwoFactorCode($user->two_factor_code));

            return redirect()->route('verify.index');
        }

        return redirect()->intended($this->redirectPath());
    }

}
