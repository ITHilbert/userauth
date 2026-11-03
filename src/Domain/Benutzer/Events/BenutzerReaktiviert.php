<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Benutzer\Events;

/**
 * Ein gelöschter Benutzer wurde für eine Neuanlage mit seiner E-Mail wiederhergestellt.
 */
final readonly class BenutzerReaktiviert
{
    public function __construct(
        public int|string $userId,
        public string $userEmail,
    ) {}
}
