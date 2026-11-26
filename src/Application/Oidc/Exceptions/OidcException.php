<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Oidc\Exceptions;

/**
 * Technischer Fehler im OIDC-Ablauf. Die Meldung nennt nur die Fehlerart (für das Log, z. B. „Signatur ungültig“),
 * nie Tokens, Codes oder das Client-Secret; Benutzer sehen stattdessen eine allgemeine, übersetzte Meldung.
 */
final class OidcException extends \RuntimeException
{
    public function __construct(string $fehlerart)
    {
        parent::__construct($fehlerart);
    }
}
