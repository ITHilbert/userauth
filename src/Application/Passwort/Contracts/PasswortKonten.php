<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Nutzerkonten aus Sicht der Passwort-Funktionen (Zurücksetzen, Ändern).
 */
interface PasswortKonten
{
    /**
     * Gespeicherte Schreibweise der E-Mail eines aktiven (nicht gelöschten) Kontos,
     * Groß-/Kleinschreibung der Eingabe egal; null, wenn kein eindeutiges Konto passt.
     */
    public function gespeicherteEmail(string $eingabe): ?string;

    public function passwortSpeichern(Authenticatable $nutzer, string $hash, CarbonImmutable $geaendertAm): void;
}
