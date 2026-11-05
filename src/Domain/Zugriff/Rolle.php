<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Zugriff;

use InvalidArgumentException;

/**
 * Wirksame Rolle eines Nutzers samt ihrer Rechte – die einzige Stelle, die entscheidet,
 * was eine Rolle darf. Ein Objekt existiert nur für eine gültige, vorhandene und nicht
 * gelöschte Rolle; wer keine solche Rolle hat, hat gar keine (null) und damit weder
 * Privilegien noch Rechte (fail-closed).
 *
 * Privilegiert ist nur Admin: die Rolle mit dem Schlüssel `admin` (exakter Vergleich), egal welche
 * ID sie hat (Entscheidung 0011). Sie hat jedes Recht und erfüllt jede Rolle. Jede andere Rolle –
 * auch eine mit dem Schlüssel `dev` oder mit ID 1 oder 2 – hat nur ihre eigenen Rechte und erfüllt
 * nur ihren eigenen Rollennamen.
 */
final readonly class Rolle
{
    /** Schlüssel (`roles.role`) der privilegierten Rolle. */
    public const string NAME_ADMIN = 'admin';

    /**
     * @param  list<string>  $rechte  Namen der Rechte der Rolle
     *
     * @throws InvalidArgumentException bei ungültiger Rollen-ID
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $rechte,
    ) {
        if ($id < 1) {
            throw new InvalidArgumentException("Ungültige Rollen-ID {$id}.");
        }
    }

    /**
     * Gültige Rollen-ID aus einem Rohwert (Datenbank, Session): nur Integer bzw. reine
     * Ziffernfolge >= 1. null, leer, 0, negativ und alles andere → null.
     * Verhindert auch die Typumwandlung der Datenbank ('1abc' = 1).
     */
    public static function idAus(mixed $wert): ?int
    {
        if (is_string($wert) && ctype_digit($wert)) {
            $wert = (int) $wert;
        }

        return is_int($wert) && $wert >= 1 ? $wert : null;
    }

    /**
     * Ist ein Rollen-Schlüssel (`roles.role`) der privilegierte – exakter Vergleich, ohne trim
     * oder andere Schreibung.
     */
    public static function istPrivilegierterSchluessel(string $schluessel): bool
    {
        return $schluessel === self::NAME_ADMIN;
    }

    public function istPrivilegiert(): bool
    {
        return self::istPrivilegierterSchluessel($this->name);
    }

    public function hatRecht(string $recht): bool
    {
        return $this->istPrivilegiert() || in_array(trim($recht), $this->rechte, true);
    }

    /**
     * Alle Rechte der Liste (UND). Eine leere Liste gewährt nichts.
     */
    public function hatAlleRechte(Namensliste $rechte): bool
    {
        if ($this->istPrivilegiert()) {
            return true;
        }

        if ($rechte->istLeer() || $rechte->hatFehlendenEintrag) {
            return false;
        }

        foreach ($rechte->namen as $recht) {
            if (! in_array($recht, $this->rechte, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mindestens ein Recht der Liste (ODER).
     */
    public function hatEinesDerRechte(Namensliste $rechte): bool
    {
        if ($this->istPrivilegiert()) {
            return true;
        }

        foreach ($rechte->namen as $recht) {
            if (in_array($recht, $this->rechte, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rollenabfrage (`hasRole`): Admin erfüllt jede Rolle (auch `dev`), alle anderen nur
     * ihren eigenen Rollennamen.
     */
    public function erfuelltRolle(string $rolle): bool
    {
        $rolle = trim($rolle);

        if ($this->istPrivilegiert()) {
            return true;
        }

        return $rolle !== '' && $this->name === $rolle;
    }

    /**
     * Trägt die Rolle einen der Namen (`hasRoleOr`) – ohne Sonderstellung für Admin.
     */
    public function traegtEinenDerNamen(Namensliste $rollen): bool
    {
        return $rollen->enthaelt($this->name);
    }
}
