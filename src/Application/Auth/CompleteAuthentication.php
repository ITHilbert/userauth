<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;
use ITHilbert\UserAuth\Mail\TwoFactorCode;

/**
 * Schritt nach erfolgreicher primärer Anmeldung (Passwort oder Social Login):
 * löst bei aktivierter 2FA Code-Erzeugung und Versand aus.
 */
final class CompleteAuthentication
{
    public function __construct(
        private readonly Repository $config,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @param  Authenticatable&object{email: string, two_factor_code: mixed}  $user
     */
    public function handle(Authenticatable $user): AuthenticationNextStep
    {
        if (! $this->config->get('userauth.two_factor_enabled')) {
            return AuthenticationNextStep::Done;
        }

        $user->generateTwoFactorCode();

        $this->mailer->to($user->email)->send(new TwoFactorCode($user->two_factor_code));

        return AuthenticationNextStep::TwoFactorRequired;
    }
}
