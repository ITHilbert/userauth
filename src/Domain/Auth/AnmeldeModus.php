<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Auth;

/**
 * Womit sich Benutzer anmelden (`userauth.anmeldung_per`): E-Mail, Benutzername oder beides.
 */
enum AnmeldeModus: string
{
    /** Anmeldung per E-Mail (Standard, Verhalten wie vor #1311). */
    case Email = 'email';

    /** Anmeldung nur per Benutzername; mehrere Konten dürfen eine E-Mail teilen. */
    case Benutzername = 'benutzername';

    /** Benutzername oder – bei genau einem Konto – E-Mail. */
    case Beides = 'beides';

    /**
     * Die Werte gelten ohne Beachtung von Groß-/Kleinschreibung und Randleerzeichen; alles andere wirkt wie `email`.
     */
    public static function ausWert(mixed $wert): self
    {
        if (! is_string($wert)) {
            return self::Email;
        }

        return self::tryFrom(strtolower(trim($wert))) ?? self::Email;
    }

    /**
     * Name des Formularfelds der Kennung (Anmeldung, „Passwort vergessen“).
     */
    public function feld(): string
    {
        return match ($this) {
            self::Email => 'email',
            self::Benutzername => 'benutzername',
            self::Beides => 'login',
        };
    }

    /**
     * Braucht der Modus die Spalte `users.benutzername`?
     */
    public function brauchtBenutzername(): bool
    {
        return $this !== self::Email;
    }
}
