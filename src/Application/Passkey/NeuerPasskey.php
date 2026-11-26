<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

/**
 * Geprüfte Registrierung: Kennung (base64url) und gespeicherte Anmeldeinformation (für den Adapter undurchsichtig).
 */
final readonly class NeuerPasskey
{
    public function __construct(
        public string $kennung,
        public string $quelle,
    ) {}
}
