<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\ConnectionInterface;
use ITHilbert\UserAuth\Application\Passwort\Contracts\PasswortHistorie;
use ITHilbert\UserAuth\Application\Passwort\Contracts\PasswortKonten;
use ITHilbert\UserAuth\Domain\Passwort\Exceptions\PasswortBereitsVerwendetException;

/**
 * Setzt ein neues Passwort: Wiederverwendung prüfen, altes Passwort in die Historie,
 * Hash und `password_changed_at` speichern. Genutzt von „Passwort ändern“ und „Passwort zurücksetzen“.
 */
final class PasswortSetzen
{
    public function __construct(
        private readonly PasswortWiederverwendung $wiederverwendung,
        private readonly PasswortHistorie $historie,
        private readonly PasswortKonten $konten,
        private readonly Hasher $hasher,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @throws PasswortBereitsVerwendetException
     */
    public function handle(Authenticatable $nutzer, #[\SensitiveParameter] string $neuesPasswort): void
    {
        $nutzerId = $nutzer->getAuthIdentifier();
        $alterHash = (string) $nutzer->getAuthPassword();

        if ($this->wiederverwendung->istWiederverwendet($nutzerId, $alterHash, $neuesPasswort)) {
            throw new PasswortBereitsVerwendetException;
        }

        $jetzt = CarbonImmutable::now();
        $neuerHash = $this->hasher->make($neuesPasswort);

        $this->db->transaction(function () use ($nutzer, $nutzerId, $alterHash, $neuerHash, $jetzt): void {
            if ($this->wiederverwendung->historieAktiv()) {
                $this->historie->merken($nutzerId, $alterHash, $jetzt);
            }

            $this->konten->passwortSpeichern($nutzer, $neuerHash, $jetzt);
        });
    }
}
