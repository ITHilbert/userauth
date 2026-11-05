<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Registrierung;

/**
 * Bindet den Bestätigungslink an die E-Mail des Kontos: Ändert sich die Adresse, passt der Link nicht mehr.
 */
final class BestaetigungsHash
{
    public static function fuer(string $email): string
    {
        return hash('sha256', $email);
    }

    public static function passt(string $email, string $hash): bool
    {
        return hash_equals(self::fuer($email), $hash);
    }
}
