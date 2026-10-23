<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use ITHilbert\UserAuth\Application\Auth\Contracts\OffeneAnmeldungen;
use ITHilbert\UserAuth\Application\Auth\Contracts\Sitzungsanmeldung;

/**
 * Abmelden bzw. Abbrechen einer offenen Zwei-Faktor-Anmeldung (AK20): verwirft die offene Anmeldung
 * und die gesamte Sitzung – auch für Gäste, die erst den ersten Faktor bestanden haben.
 */
final class Abmelden
{
    public function __construct(
        private readonly OffeneAnmeldungen $anmeldungen,
        private readonly Sitzungsanmeldung $sitzung,
    ) {}

    public function handle(): void
    {
        $this->anmeldungen->verwerfen();
        $this->sitzung->abmelden();
    }
}
