<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort\Contracts;

use Carbon\CarbonImmutable;

/**
 * Früher verwendete Passwort-Hashes eines Nutzers.
 */
interface PasswortHistorie
{
    /**
     * @return list<string> neueste zuerst
     */
    public function letzteHashes(int|string $nutzerId, int $anzahl): array;

    public function merken(int|string $nutzerId, string $hash, CarbonImmutable $zeitpunkt): void;
}
