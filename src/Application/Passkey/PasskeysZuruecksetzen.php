<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use Illuminate\Contracts\Events\Dispatcher;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyAnmeldeinformationen;
use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyBenutzerNichtEindeutigException;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialUserRepository;
use ITHilbert\UserAuth\Application\SocialLogin\LocalUser;
use ITHilbert\UserAuth\Domain\Passkey\Events\PasskeysZurueckgesetzt;

/**
 * Anwendungsfall: alle Passkeys eines Benutzers löschen – durch einen Administrator (AK15) oder per
 * Artisan-Befehl zur E-Mail eines aktiven Benutzers (AK16). Das Ereignis landet im Audit-Log.
 */
final class PasskeysZuruecksetzen
{
    public function __construct(
        private readonly PasskeyAnmeldeinformationen $passkeys,
        private readonly SocialUserRepository $benutzer,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return int|null Anzahl der gelöschten Passkeys; null, wenn es keinen aktiven Benutzer mit der ID gibt
     */
    public function fuerBenutzer(int|string $userId): ?int
    {
        $konto = $this->benutzer->findActiveById($userId);

        return $konto === null ? null : $this->zuruecksetzen($konto);
    }

    /**
     * @throws PasskeyBenutzerNichtEindeutigException keiner oder mehrere aktive Benutzer mit der E-Mail
     */
    public function perEmail(string $email): int
    {
        $anzahl = $this->benutzer->countActiveByEmail($email);
        $konto = $anzahl === 1 ? $this->benutzer->findActiveByEmail($email) : null;

        if ($konto === null) {
            throw new PasskeyBenutzerNichtEindeutigException($anzahl);
        }

        return $this->zuruecksetzen($konto);
    }

    private function zuruecksetzen(LocalUser $konto): int
    {
        $geloescht = $this->passkeys->alleLoeschen($konto->id);
        $this->events->dispatch(new PasskeysZurueckgesetzt($konto->id, $konto->email, $geloescht));

        return $geloescht;
    }
}
