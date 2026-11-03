<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\ViewComposers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Router;
use ITHilbert\UserAuth\Application\Registrierung\RegistrierungsEinstellungen;

/**
 * Login-Seite: Link „Registrieren“ nur bei eingeschalteter Registrierung – und nur, wenn die Routen registriert
 * sind (ein Routen-Cache aus der Zeit vor dem Einschalten würde sonst jede Login-Seite mit 500 abbrechen).
 */
final class RegistrierungComposer
{
    public function __construct(
        private readonly RegistrierungsEinstellungen $einstellungen,
        private readonly Router $router,
    ) {}

    public function compose(View $view): void
    {
        $view->with('registrierungAktiv', $this->einstellungen->aktiv() && $this->router->has('register'));
    }
}
