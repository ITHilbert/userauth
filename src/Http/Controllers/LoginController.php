<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use ITHilbert\UserAuth\Application\Auth\Abmelden;
use ITHilbert\UserAuth\Application\Auth\AuthenticationNextStep;
use ITHilbert\UserAuth\Application\Auth\MitPasswortAnmelden;
use ITHilbert\UserAuth\Traits\Auth\AuthenticatesUsers;

/**
 * Anmeldung mit E-Mail und Passwort; Validierung und Drosselung (E-Mail + IP) aus dem Trait AuthenticatesUsers.
 */
final class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function __construct(
        private readonly MitPasswortAnmelden $mitPasswortAnmelden,
        private readonly Abmelden $abmelden,
    ) {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm(): View
    {
        return view('userauth::login');
    }

    /**
     * @throws ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $this->validateLogin($request);

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request); // wirft ValidationException (429)
        }

        $schritt = $this->mitPasswortAnmelden->handle($this->credentials($request), $request->filled('remember'));

        if ($schritt === null) {
            $this->incrementLoginAttempts($request);
            $this->sendFailedLoginResponse($request); // wirft ValidationException
        }

        $this->clearLoginAttempts($request);

        return match ($schritt) {
            AuthenticationNextStep::TwoFactorRequired => redirect()->route('verify.index'),
            AuthenticationNextStep::CodeNichtVersendet => redirect()->route('login')->withErrors(['two_factor_code' => __('userauth::zwei_faktor.versand_fehlgeschlagen')]),
            AuthenticationNextStep::Done => redirect()->intended($this->redirectPath()),
        };
    }

    /**
     * Abmelden bzw. offene Zwei-Faktor-Anmeldung abbrechen (auch als Gast, AK20).
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->abmelden->handle();

        return redirect(config('userauth.redirect_after_logout', '/home'));
    }

    public function redirectPath(): string
    {
        return (string) config('userauth.redirect_after_login', '/home');
    }
}
