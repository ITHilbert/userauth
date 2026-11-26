<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\ViewComposers;

use Illuminate\Contracts\View\View;
use ITHilbert\UserAuth\Application\Oidc\OidcSettings;
use ITHilbert\UserAuth\Application\Oidc\OidcVerfuegbarkeit;

/**
 * Login-Seite: Text des OIDC-Knopfs, nur wenn OIDC eingeschaltet und konfiguriert ist (sonst null).
 */
final class OidcKnopfComposer
{
    public function __construct(
        private readonly OidcVerfuegbarkeit $verfuegbarkeit,
        private readonly OidcSettings $settings,
    ) {}

    public function compose(View $view): void
    {
        $view->with('oidcKnopf', $this->verfuegbarkeit->verfuegbar() ? $this->settings->buttonText() : null);
    }
}
