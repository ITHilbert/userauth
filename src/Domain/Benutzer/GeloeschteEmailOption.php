<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Benutzer;

/**
 * Was mit der E-Mail eines gelöschten Benutzers bei einer Neuanlage geschieht (`userauth.geloeschte_email`).
 */
enum GeloeschteEmailOption: string
{
    /** Die E-Mail bleibt belegt, die Neuanlage wird abgelehnt (Standard). */
    case Sperren = 'sperren';

    /** Die zuletzt gelöschte Zeile wird wiederhergestellt und bereinigt. */
    case Reaktivieren = 'reaktivieren';

    /** Die gelöschten Zeilen werden endgültig entfernt, danach wird normal angelegt. */
    case Neu = 'neu';

    /**
     * Nur die exakten Werte `sperren`, `reaktivieren` und `neu` gelten; alles andere wirkt wie `sperren`.
     */
    public static function ausWert(mixed $wert): self
    {
        return is_string($wert) ? (self::tryFrom($wert) ?? self::Sperren) : self::Sperren;
    }
}
