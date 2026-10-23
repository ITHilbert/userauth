<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ITHilbert\UserAuth\Application\Auth\AuthenticationNextStep;
use ITHilbert\UserAuth\Application\SocialLogin\ConnectSocialAccount;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Application\SocialLogin\Exceptions\SocialProviderUnavailableException;
use ITHilbert\UserAuth\Application\SocialLogin\HandleSocialLogin;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialLoginException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

final class SocialLoginController extends Controller
{
    public function redirect(Request $request, SocialProvider $provider, SocialIdentityProvider $identityProvider): RedirectResponse
    {
        try {
            return redirect()->away($identityProvider->authorizationUrl($provider));
        } catch (SocialProviderUnavailableException) {
            return $this->failure($request->user() !== null, __('userauth::social.errors.provider_unavailable'));
        }
    }

    public function callback(
        Request $request,
        SocialProvider $provider,
        HandleSocialLogin $login,
        ConnectSocialAccount $connect,
    ): RedirectResponse {
        $connecting = $request->user() !== null;

        try {
            if ($connecting) {
                $connect->handle($request->user()->getAuthIdentifier(), $provider);

                return redirect()->route('userauth.social.accounts')
                    ->with('status', __('userauth::social.status.connected', ['provider' => $provider->label()]));
            }

            $result = $login->handle($provider);
        } catch (SocialProviderUnavailableException) {
            return $this->failure($connecting, __('userauth::social.errors.provider_unavailable'));
        } catch (SocialLoginException $e) {
            return $this->failure($connecting, $e->getMessage());
        }

        if ($result->nextStep === null) {
            return redirect()->route('login')->with('status', __('userauth::social.status.confirmation_sent'));
        }

        return match ($result->nextStep) {
            AuthenticationNextStep::TwoFactorRequired => redirect()->route('verify.index'),
            AuthenticationNextStep::CodeNichtVersendet => $this->failure(false, __('userauth::zwei_faktor.versand_fehlgeschlagen')),
            AuthenticationNextStep::Done => redirect()->intended(config('userauth.redirect_after_login', '/home')),
        };
    }

    private function failure(bool $connecting, string $message): RedirectResponse
    {
        return redirect()->route($connecting ? 'userauth.social.accounts' : 'login')
            ->withErrors(['social' => $message]);
    }
}
