<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\ViewComposers;

use Illuminate\Contracts\View\View;
use ITHilbert\UserAuth\Application\Passkey\PasskeyEinstellungen;
use ITHilbert\UserAuth\Domain\Passkey\PasswortLoginModus;
use ITHilbert\UserAuth\Infrastructure\Assets\AuthAssetVersion;

/**
 * Login-Seite: Passkey-Knopf samt Skript nur bei aktivem Passkey (`passkeyAktiv`, `passkeySkript`), das
 * Passwortformular nur, solange `userauth.passwort_login` nicht `nie` ist (`passwortFormular`, AK2).
 */
final class PasskeyKnopfComposer
{
    public function __construct(
        private readonly PasskeyEinstellungen $einstellungen,
        private readonly AuthAssetVersion $assets,
    ) {}

    public function compose(View $view): void
    {
        $aktiv = $this->einstellungen->enabled();

        $view->with([
            'passkeyAktiv' => $aktiv,
            'passkeySkript' => $aktiv ? route('userauth.assets.passkey', ['v' => $this->assets->passkeyJsVersion()]) : null,
            'passwortFormular' => $this->einstellungen->passwortLoginModus() !== PasswortLoginModus::Nie,
        ]);
    }
}
