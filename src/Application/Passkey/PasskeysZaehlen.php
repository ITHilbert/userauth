<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyAnmeldeinformationen;

/**
 * Anzahl der Passkeys eines Benutzers für die Benutzerverwaltung; null, solange Passkeys aus sind (AK15).
 */
final class PasskeysZaehlen
{
    public function __construct(
        private readonly PasskeyAnmeldeinformationen $passkeys,
        private readonly PasskeyEinstellungen $einstellungen,
    ) {}

    public function handle(int|string $userId): ?int
    {
        return $this->einstellungen->enabled() ? $this->passkeys->anzahl($userId) : null;
    }
}
