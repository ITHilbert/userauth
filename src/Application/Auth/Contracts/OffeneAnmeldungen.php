<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth\Contracts;

use ITHilbert\UserAuth\Domain\Auth\OffeneAnmeldung;

/**
 * Ablage der offenen Zwei-Faktor-Anmeldung – ausschließlich in der eigenen Sitzung (AK17).
 */
interface OffeneAnmeldungen
{
    /**
     * Erneuert die Sitzungs-ID und legt die offene Anmeldung ab (ersetzt eine bisherige).
     */
    public function beginnen(OffeneAnmeldung $anmeldung): void;

    public function speichern(OffeneAnmeldung $anmeldung): void;

    public function laden(): ?OffeneAnmeldung;

    public function verwerfen(): void;

    /**
     * Entnimmt das ursprünglich gewünschte Ziel (z. B. `url.intended`), damit es bis nach der 2FA erhalten bleibt.
     */
    public function zielEntnehmen(): ?string;
}
