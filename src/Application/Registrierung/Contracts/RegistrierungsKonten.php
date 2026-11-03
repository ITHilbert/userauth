<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Registrierung\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use ITHilbert\UserAuth\Application\Registrierung\RegistriertesKonto;

/**
 * Port: Konten der Selbstregistrierung. „Bestätigung offen“ = über die Registrierung entstanden und
 * `email_verified_at` leer.
 */
interface RegistrierungsKonten
{
    /**
     * Gibt es ein Konto mit dieser E-Mail – Groß-/Kleinschreibung egal, auch gelöschte?
     */
    public function emailVergeben(string $email): bool;

    /**
     * Legt das Konto mit „Bestätigung offen“ an; null, wenn die E-Mail inzwischen vergeben ist.
     */
    public function anlegen(string $name, string $email, string $passwortHash, ?int $rolleId, CarbonImmutable $jetzt): ?RegistriertesKonto;

    /**
     * Nicht gelöschtes Konto mit „Bestätigung offen“ zur E-Mail (Groß-/Kleinschreibung egal).
     */
    public function offenesKontoZurEmail(string $email): ?RegistriertesKonto;

    /**
     * Nicht gelöschtes Konto zur ID.
     */
    public function aktivesKonto(string $id): ?RegistriertesKonto;

    /**
     * Setzt `email_verified_at`, falls noch leer; true, wenn dieser Aufruf bestätigt hat.
     */
    public function alsBestaetigtMarkieren(int|string $id, CarbonImmutable $jetzt): bool;

    public function bestaetigungOffen(Authenticatable $nutzer): bool;
}
