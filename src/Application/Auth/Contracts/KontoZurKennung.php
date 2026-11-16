<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth\Contracts;

use ITHilbert\UserAuth\Domain\Auth\AnmeldeModus;

/**
 * Findet zu einer Kennung (Benutzername bzw. Benutzername oder E-Mail) genau ein nicht gelöschtes Konto.
 * Benutzername ohne Beachtung der Schreibweise, um Randleerzeichen bereinigt; im Modus `beides` hat ein
 * Benutzername Vorrang, sonst gilt eine E-Mail nur, wenn genau ein Konto sie hat.
 */
interface KontoZurKennung
{
    /**
     * @return int|string|null Schlüssel des Kontos; null bei leerer, unbekannter oder mehrdeutiger Kennung
     *
     * @throws \RuntimeException Konfigurationsfehler: Modus braucht die Spalte `users.benutzername`, die fehlt
     */
    public function finden(AnmeldeModus $modus, string $kennung): int|string|null;
}
