<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\ViewComposers;

use Illuminate\Contracts\View\View;
use ITHilbert\UserAuth\Application\Registrierung\RegistrierungsEinstellungen;

/**
 * Login-Seite: Link „Registrieren“ nur bei eingeschalteter Registrierung – zur Laufzeit aus der Config
 * (die Routen sind immer registriert, siehe EnsureRegistrationEnabled).
 */
final class RegistrierungComposer
{
    public function __construct(private readonly RegistrierungsEinstellungen $einstellungen) {}

    public function compose(View $view): void
    {
        $view->with('registrierungAktiv', $this->einstellungen->aktiv());
    }
}
