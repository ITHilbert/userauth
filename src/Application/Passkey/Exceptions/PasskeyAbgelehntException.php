<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey\Exceptions;

/**
 * Anmeldung oder Registrierung per Passkey abgelehnt. Die Meldung nennt nur die Fehlerart (für das Log, z. B.
 * „Signatur ungültig“, „Challenge abgelaufen“), nie Challenge, Antwort des Browsers oder Schlüssel; Benutzer sehen
 * stattdessen eine allgemeine, übersetzte Meldung.
 */
final class PasskeyAbgelehntException extends \RuntimeException
{
    public function __construct(string $fehlerart)
    {
        parent::__construct($fehlerart);
    }
}
