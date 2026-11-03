<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Registrierung\Events;

/**
 * Die E-Mail eines registrierten Kontos wurde über den Bestätigungslink bestätigt (nur beim ersten Aufruf).
 */
final readonly class EmailBestaetigt
{
    public function __construct(
        public int|string $nutzerId,
        public string $email,
    ) {}
}
