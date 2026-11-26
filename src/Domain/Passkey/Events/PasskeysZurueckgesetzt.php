<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Passkey\Events;

/**
 * Alle Passkeys eines Benutzers wurden gelöscht (Administrator oder Artisan-Befehl).
 */
final readonly class PasskeysZurueckgesetzt
{
    public function __construct(
        public int|string $userId,
        public string $userEmail,
        public int $anzahl,
    ) {}
}
