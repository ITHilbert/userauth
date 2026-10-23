<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort;

enum PasswortZuruecksetzenErgebnis
{
    case Zurueckgesetzt;

    /** Token falsch, abgelaufen, verbraucht oder zu einer anderen bzw. unbekannten E-Mail. */
    case LinkUngueltig;
}
