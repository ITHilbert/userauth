<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Registrierung\Contracts;

/**
 * Stellt den Bestätigungslink zu. Der Versand darf die Antwortzeit nicht beeinflussen (Entscheidung 0008).
 */
interface BestaetigungsLinkVersand
{
    public function senden(string $adresse, string $bestaetigungsUrl, int $gueltigMinuten): void;
}
