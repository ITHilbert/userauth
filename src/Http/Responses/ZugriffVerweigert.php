<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Responses;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Einheitliche Antwort der Zugriffs-Middlewares: Gäste zur Anmeldung, fehlende Rechte
 * je nach `userauth.redirect_on_no_permission` zur Anmeldung ('login'), zur Seite `no-permission` oder ('403') mit Status 403 (HTML und JSON).
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
        $ziel = $this->config->get('userauth.redirect_on_no_permission', 'login');

        if ($ziel === 'login') {
            return $this->nichtAngemeldet();
        }

        if ($ziel === '403') {
            abort(403, __('userauth::permission.no-permission'));
        }

        return $this->redirect->route('no-permission');
    }
}
