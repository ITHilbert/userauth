<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Http\Middleware\TwoFactorMiddleware;

/**
 * Markiert jede Anmeldung (Passwort/2FA, Social Login, „Angemeldet bleiben“, Impersonation) als nach dem Umstieg
 * auf die sitzungsgebundene 2FA entstanden – siehe TwoFactorMiddleware.
 */
final class SitzungsgebundeneAnmeldungMarkieren
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if ($this->request->hasSession()) {
            $this->request->session()->put(TwoFactorMiddleware::MARKIERUNG, true);
        }
    }
}
