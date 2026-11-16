<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use ITHilbert\UserAuth\Application\Passwort\ResetKonto;
use ITHilbert\UserAuth\Domain\Auth\AnmeldeModus;

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

    /**
     * Genau ein aktives Konto zur Kennung (Modus `benutzername`/`beides`, Regeln wie bei der Anmeldung);
     * null bei unbekannter, mehrdeutiger oder leerer Kennung.
     */
    public function zurKennung(AnmeldeModus $modus, string $kennung): ?ResetKonto;

    public function passwortSpeichern(Authenticatable $nutzer, string $hash, CarbonImmutable $geaendertAm): void;
}
