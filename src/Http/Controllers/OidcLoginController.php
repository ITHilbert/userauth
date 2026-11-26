<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ITHilbert\UserAuth\Application\Oidc\Exceptions\OidcException;
use ITHilbert\UserAuth\Application\Oidc\HandleOidcLogin;
use ITHilbert\UserAuth\Application\Oidc\StartOidcLogin;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialLoginException;

/**
 * Anmeldung über den eigenen Anmeldedienst (OIDC, z. B. Authelia). Fehler führen immer auf `login?lokal=1`,
 * damit der Auto-Redirect der Login-Seite keine Schleife bildet (AK15).
 */
final class OidcLoginController extends Controller
{
    public function redirect(StartOidcLogin $start): RedirectResponse
    {
        try {
            return redirect()->away($start->handle());
        } catch (OidcException) {
            return $this->abgelehnt(__('userauth::oidc.errors.allgemein'));
        }
    }

    public function callback(Request $request, HandleOidcLogin $login): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect(config('userauth.redirect_after_login', '/home'));
        }

        try {
            $login->handle($this->parameter($request, 'state'), $this->parameter($request, 'code'), $this->parameter($request, 'error'));
        } catch (OidcException) {
            return $this->abgelehnt(__('userauth::oidc.errors.allgemein'));
        } catch (SocialLoginException $e) {
            return $this->abgelehnt($e->getMessage());
        }

        return redirect()->intended(config('userauth.redirect_after_login', '/home'));
    }

    private function parameter(Request $request, string $name): ?string
    {
        $wert = $request->query($name);

        return is_string($wert) ? $wert : null;
    }

    private function abgelehnt(string $meldung): RedirectResponse
    {
        return redirect()->route('login', ['lokal' => 1])->withErrors(['oidc' => $meldung]);
    }
}
