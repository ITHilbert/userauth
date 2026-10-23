<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

enum ZweiFaktorStatus
{
    /** Code richtig: angemeldet. */
    case Bestanden;

    /** Code falsch; weitere Versuche möglich. */
    case Abgelehnt;

    /** Code abgelaufen; neuer Code nötig. */
    case Abgelaufen;

    /** Zu viele Fehlversuche: offene Anmeldung verworfen, neu anmelden (AK16). */
    case Gesperrt;

    /** In dieser Sitzung wartet keine Anmeldung auf einen Code. */
    case KeineOffeneAnmeldung;
}
