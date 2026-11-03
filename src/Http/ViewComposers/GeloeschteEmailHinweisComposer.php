<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\ViewComposers;

use Illuminate\Contracts\View\View;
use ITHilbert\UserAuth\Application\Benutzer\GeloeschteEmailAufloesen;
use ITHilbert\UserAuth\Application\SocialLogin\SocialLoginSettings;
use ITHilbert\UserAuth\Domain\Benutzer\GeloeschteEmailOption;

/**
 * Hinweis an den Social-Schaltflächen der Login-Seite: Bei Option `neu` und erlaubter Social-Registrierung wird ein
 * gelöschtes Konto mit derselben E-Mail endgültig entfernt. Die View zeigt ihn nur, wenn ein Anbieter verfügbar ist.
 */
final class GeloeschteEmailHinweisComposer
{
    public function __construct(
        private readonly GeloeschteEmailAufloesen $geloeschteEmail,
        private readonly SocialLoginSettings $settings,
    ) {}

    public function compose(View $view): void
    {
        $view->with(
            'geloeschteEmailHinweis',
            $this->settings->allowRegistration() && $this->geloeschteEmail->option() === GeloeschteEmailOption::Neu,
        );
    }
}
