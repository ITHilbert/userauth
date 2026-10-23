<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use ITHilbert\UserAuth\Application\Passwort\Contracts\PasswortHistorie;

/**
 * Passwort-Richtlinie „keine Wiederverwendung“ (`userauth.password_policy`): einzige Stelle,
 * die prüft, ob ein Kandidat dem aktuellen oder einem der letzten Passwörter entspricht.
 */
final class PasswortWiederverwendung
{
    public function __construct(
        private readonly Repository $config,
        private readonly Hasher $hasher,
        private readonly PasswortHistorie $historie,
    ) {}

    /**
     * Wird bei einer Passwortänderung das alte Passwort in die Historie geschrieben?
     */
    public function historieAktiv(): bool
    {
        return (bool) $this->config->get('userauth.password_policy.enabled', false);
    }

    public function istWiederverwendet(int|string $nutzerId, string $aktuellerHash, string $kandidat): bool
    {
        if (! $this->historieAktiv()) {
            return false;
        }

        $anzahl = (int) $this->config->get('userauth.password_policy.prevent_reuse_last_passwords', 3);

        if ($anzahl <= 0) {
            return false;
        }

        if ($aktuellerHash !== '' && $this->hasher->check($kandidat, $aktuellerHash)) {
            return true;
        }

        foreach ($this->historie->letzteHashes($nutzerId, $anzahl) as $alterHash) {
            if ($this->hasher->check($kandidat, $alterHash)) {
                return true;
            }
        }

        return false;
    }
}
