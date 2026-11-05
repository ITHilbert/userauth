<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Benutzer\Contracts;

use ITHilbert\UserAuth\Application\Benutzer\GeloeschterBenutzer;

/**
 * Datenzugriffe auf weich gelöschte Benutzer und ihre Verknüpfungen.
 * E-Mails werden ohne Rücksicht auf Groß-/Kleinschreibung verglichen.
 */
interface GeloeschteBenutzer
{
    public function existiert(string $email): bool;

    /**
     * Für die laufende Transaktion gesperrt, zuletzt gelöschter zuerst.
     *
     * @return list<GeloeschterBenutzer>
     */
    public function mitEmail(string $email): array;

    /**
     * Stellt die Zeile wieder her und bereinigt sie: Social-Konten, offene Verknüpfungen und Team-Mitgliedschaften
     * entfernt, aktives Team und Zwei-Faktor-Code geleert, `remember_token` neu, Reset-Tokens zur E-Mail gelöscht.
     * Passwort-Historie und Audit-Log bleiben.
     */
    public function wiederherstellen(GeloeschterBenutzer $benutzer): void;

    /**
     * Entfernt die Zeile endgültig samt Social-Konten, offenen Verknüpfungen, Team-Mitgliedschaften,
     * Passwort-Historie und Reset-Tokens zur E-Mail. Das Audit-Log bleibt.
     */
    public function endgueltigLoeschen(GeloeschterBenutzer $benutzer): void;
}
