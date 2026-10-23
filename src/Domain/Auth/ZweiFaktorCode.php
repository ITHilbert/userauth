<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Auth;

use InvalidArgumentException;

/**
 * Sechsstelliger Einmal-Code der Zwei-Faktor-Anmeldung aus kryptografisch sicherem Zufall (AK18).
 * Nur der Versand bekommt den Klartext; gespeichert wird ausschließlich sein Hash.
 */
final readonly class ZweiFaktorCode
{
    public const STELLEN = 6;

    public function __construct(#[\SensitiveParameter] public string $wert)
    {
        if (preg_match('/^\d{'.self::STELLEN.'}$/', $wert) !== 1) {
            throw new InvalidArgumentException('Ein Zwei-Faktor-Code besteht aus genau '.self::STELLEN.' Ziffern.');
        }
    }

    public static function erzeugen(): self
    {
        $hoechsterWert = (10 ** self::STELLEN) - 1;

        return new self(str_pad((string) random_int(0, $hoechsterWert), self::STELLEN, '0', STR_PAD_LEFT));
    }
}
