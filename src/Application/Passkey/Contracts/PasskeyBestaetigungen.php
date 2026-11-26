<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey\Contracts;

/**
 * Port: Bestätigung vor „Passkey hinzufügen“ (passkey-bestaetigung, #2225). Sie gilt nur in dieser Sitzung und nur
 * für den Benutzer, für den sie erteilt wurde.
 */
interface PasskeyBestaetigungen
{
    /**
     * Merkt die Bestätigung für den Benutzer bis zum Zeitpunkt (Unix-Sekunden); eine ältere wird ersetzt.
     */
    public function merken(int|string $userId, int $bis): void;

    /**
     * Zeitpunkt (Unix-Sekunden), bis zu dem die Bestätigung für den Benutzer gilt; null, wenn keine für ihn vorliegt.
     */
    public function gueltigBis(int|string $userId): ?int;

    /**
     * Entfernt die Bestätigung (Anmelden, Abmelden).
     */
    public function vergessen(): void;
}
