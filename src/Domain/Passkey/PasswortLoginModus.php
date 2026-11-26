<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Passkey;

/**
 * Wann die Anmeldung mit Passwort erlaubt ist (`userauth.passwort_login`).
 */
enum PasswortLoginModus: string
{
    /** Passwort-Anmeldung für alle (Standard, Verhalten wie vor #2220). */
    case Immer = 'immer';

    /** Gesperrt für Benutzer mit mindestens einem Passkey: der Passkey ersetzt das Passwort. */
    case OhnePasskey = 'ohne_passkey';

    /** Gesperrt für alle (Anmeldung nur über Passkey, OIDC oder Social Login). */
    case Nie = 'nie';

    /**
     * Die Werte gelten ohne Beachtung von Groß-/Kleinschreibung und Randleerzeichen; alles andere wirkt wie `immer`.
     */
    public static function ausWert(mixed $wert): self
    {
        if (! is_string($wert)) {
            return self::Immer;
        }

        return self::tryFrom(strtolower(trim($wert))) ?? self::Immer;
    }
}
