<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Responses;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Einheitliche Antwort der Zugriffs-Middlewares: Gäste zur Anmeldung, fehlende Rechte
 * je nach `userauth.redirect_on_no_permission` zur Anmeldung ('login') oder zur Seite `no-permission`.
 */
final class ZugriffVerweigert
{
    public function __construct(
        private readonly Config $config,
        private readonly Redirector $redirect,
    ) {}

    public function nichtAngemeldet(): RedirectResponse
    {
        return $this->redirect->guest($this->redirect->getUrlGenerator()->route('login'));
    }

    public function fehlendesRecht(): RedirectResponse
    {
        if ($this->config->get('userauth.redirect_on_no_permission', 'login') === 'login') {
            return $this->nichtAngemeldet();
        }

        return $this->redirect->route('no-permission');
    }
}
