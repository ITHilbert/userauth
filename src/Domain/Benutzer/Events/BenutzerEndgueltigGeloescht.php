<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Benutzer\Events;

/**
 * Ein gelöschter Benutzer wurde für eine Neuanlage mit seiner E-Mail endgültig entfernt.
 */
final readonly class BenutzerEndgueltigGeloescht
{
    public function __construct(
        public int|string $userId,
        public string $userEmail,
    ) {}
}
