<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort\Contracts;

/**
 * Stellt den Reset-Link zu. Der Versand darf die Antwortzeit nicht beeinflussen (Entscheidung 0008).
 */
interface ResetLinkVersand
{
    public function senden(string $adresse, string $resetUrl, int $gueltigMinuten): void;
}
