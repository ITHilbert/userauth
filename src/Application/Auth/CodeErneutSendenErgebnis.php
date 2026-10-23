<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

enum CodeErneutSendenErgebnis
{
    case Gesendet;

    /** Letzter erneuter Versand liegt keine 60 Sekunden zurück (AK19). */
    case ZuFrueh;

    case KeineOffeneAnmeldung;
}
