<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyAnmeldeinformationen;

/**
 * Anwendungsfall: einen eigenen Passkey löschen, auch den letzten (AK11).
 */
final class PasskeyLoeschen
{
    public function __construct(private readonly PasskeyAnmeldeinformationen $passkeys) {}

    /**
     * @return bool false, wenn der Passkey nicht existiert oder einem anderen Benutzer gehört
     */
    public function handle(int|string $userId, int $passkeyId): bool
    {
        return $this->passkeys->loeschen($userId, $passkeyId);
    }
}
