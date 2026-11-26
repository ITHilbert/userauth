<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey\Exceptions;

/**
 * Zur E-Mail gibt es keinen oder mehr als einen aktiven Benutzer (Passkeys zurücksetzen per Artisan-Befehl).
 */
final class PasskeyBenutzerNichtEindeutigException extends \RuntimeException
{
    public function __construct(public readonly int $anzahl)
    {
        parent::__construct($anzahl === 0 ? 'Kein aktiver Benutzer mit dieser E-Mail.' : 'Mehrere aktive Benutzer mit dieser E-Mail.');
    }
}
