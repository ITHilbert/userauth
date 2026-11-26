<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use Illuminate\Contracts\Auth\Authenticatable;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyAnmeldeinformationen;
use ITHilbert\UserAuth\Domain\Passkey\PasswortLoginModus;

/**
 * Ist die Anmeldung mit Passwort für den Benutzer gesperrt (`userauth.passwort_login`, AK12, AK13)?
 */
final class PasswortLoginSperre
{
    public function __construct(
        private readonly PasskeyEinstellungen $einstellungen,
        private readonly PasskeyAnmeldeinformationen $passkeys,
    ) {}

    public function gesperrt(Authenticatable $nutzer): bool
    {
        return match ($this->einstellungen->passwortLoginModus()) {
            PasswortLoginModus::Immer => false,
            PasswortLoginModus::Nie => true,
            PasswortLoginModus::OhnePasskey => $this->passkeys->anzahl($nutzer->getAuthIdentifier()) > 0,
        };
    }
}
