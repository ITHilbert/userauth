<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use Carbon\CarbonImmutable;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyAnmeldeinformationen;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVerfahren;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVorgaenge;
use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyAbgelehntException;

/**
 * Anwendungsfall: Anmeldeoptionen für die Bestätigung mit einem vorhandenen Passkey (passkey-bestaetigung, AK5).
 * Erlaubt sind nur die Passkeys des angemeldeten Benutzers; die Challenge liegt getrennt von Anmeldung und
 * Registrierung (Art `Bestaetigung`) und gilt nur für diesen Benutzer.
 */
final class PasskeyBestaetigungVorbereiten
{
    public function __construct(
        private readonly PasskeyVerfahren $verfahren,
        private readonly PasskeyVorgaenge $vorgaenge,
        private readonly PasskeyAnmeldeinformationen $passkeys,
        private readonly PasskeyEinstellungen $einstellungen,
    ) {}

    /**
     * @return array<string, mixed> Optionen für `navigator.credentials.get`
     *
     * @throws PasskeyAbgelehntException Benutzer hat keinen Passkey
     */
    public function handle(int|string $userId): array
    {
        $kennungen = $this->passkeys->kennungenDesBenutzers($userId);
        if ($kennungen === []) {
            throw new PasskeyAbgelehntException('Kein Passkey zur Bestätigung');
        }

        $optionen = $this->verfahren->anmeldeOptionen($this->einstellungen->partei(), $kennungen);
        $this->vorgaenge->merken(
            PasskeyVorgangsArt::Bestaetigung,
            new PasskeyVorgang($optionen->zustand, CarbonImmutable::now()->getTimestamp(), (string) $userId),
        );

        return $optionen->optionen;
    }
}
