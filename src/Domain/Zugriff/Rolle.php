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
 * Privilegiert sind Dev und Admin: Sie haben jedes Recht. Dev ist die Rolle 1 mit dem Schlüssel
 * `dev`, Admin die Rolle 2 mit dem Schlüssel `admin` (Seeder) – ID **und** Schlüssel müssen passen,
 * sonst ist die Rolle nicht privilegiert (z. B. eine Rolle `editor`, die zufällig ID 1 hat).
 * Für Rollenabfragen gilt wie bisher: Dev erfüllt jede Rolle, Admin jede außer `dev`.
 */
final readonly class Rolle
{
    public const int DEV = 1;

    public const int ADMIN = 2;

    public const string NAME_DEV = 'dev';

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

    public function istDev(): bool
    {
        return $this->id === self::DEV && $this->name === self::NAME_DEV;
    }

    public function istAdmin(): bool
    {
        return $this->id === self::ADMIN && $this->name === self::NAME_ADMIN;
    }

    public function istPrivilegiert(): bool
    {
        return $this->istDev() || $this->istAdmin();
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

        if ($rechte->istLeer()) {
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
     * Rollenabfrage (`hasRole`): Dev erfüllt jede Rolle, Admin jede außer `dev`,
     * alle anderen nur ihren eigenen Rollennamen.
     */
    public function erfuelltRolle(string $rolle): bool
    {
        $rolle = trim($rolle);

        if ($this->istDev()) {
            return true;
        }

        if ($this->istAdmin() && $rolle !== self::NAME_DEV) {
            return true;
        }

        return $rolle !== '' && $this->name === $rolle;
    }

    /**
     * Trägt die Rolle einen der Namen (`hasRoleOr`) – ohne Sonderstellung für Dev/Admin.
     */
    public function traegtEinenDerNamen(Namensliste $rollen): bool
    {
        return $rollen->enthaelt($this->name);
    }
}
