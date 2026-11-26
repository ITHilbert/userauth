<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use Carbon\CarbonImmutable;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyAnmeldeinformationen;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVerfahren;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVorgaenge;
use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyAbgelehntException;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialUserRepository;

/**
 * Anwendungsfall: Registrierungsoptionen für den angemeldeten Benutzer (AK9). Die schon eingetragenen Passkeys
 * stehen in `excludeCredentials`; der Vorgang gilt nur für diesen Benutzer.
 */
final class PasskeyRegistrierungVorbereiten
{
    public function __construct(
        private readonly PasskeyVerfahren $verfahren,
        private readonly PasskeyVorgaenge $vorgaenge,
        private readonly PasskeyAnmeldeinformationen $passkeys,
        private readonly PasskeyEinstellungen $einstellungen,
        private readonly SocialUserRepository $benutzer,
    ) {}

    /**
     * @return array<string, mixed> Optionen für `navigator.credentials.create`
     *
     * @throws PasskeyAbgelehntException Benutzer nicht (mehr) aktiv
     */
    public function handle(int|string $userId): array
    {
        $konto = $this->benutzer->findActiveById($userId)
            ?? throw new PasskeyAbgelehntException('Benutzer gelöscht oder deaktiviert');

        $optionen = $this->verfahren->registrierungsOptionen(
            $this->einstellungen->partei(),
            new PasskeyBenutzer($this->einstellungen->benutzerHandle($konto->id), $konto->email, $konto->name ?? $konto->email),
            $this->passkeys->kennungenDesBenutzers($konto->id),
        );

        $this->vorgaenge->merken(
            PasskeyVorgangsArt::Registrierung,
            new PasskeyVorgang($optionen->zustand, CarbonImmutable::now()->getTimestamp(), (string) $konto->id),
        );

        return $optionen->optionen;
    }
}
