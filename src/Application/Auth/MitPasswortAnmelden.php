<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use ITHilbert\UserAuth\Application\Auth\Contracts\Anmeldedaten;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\RegistrierungsKonten;
use ITHilbert\UserAuth\Domain\Registrierung\Exceptions\EmailNochNichtBestaetigtException;

/**
 * Anmeldung mit E-Mail und Passwort. Die Zugangsdaten werden nur geprüft; angemeldet wird in
 * CompleteAuthentication – bei aktiver 2FA erst nach dem Code (AK14).
 * Konten aus der Selbstregistrierung mit offener Bestätigung werden nicht angemeldet (auch kein 2FA-Code);
 * andere Konten mit leerem `email_verified_at` (Admin, Seeder, Social Login) melden sich an wie bisher.
 */
final class MitPasswortAnmelden
{
    public function __construct(
        private readonly Anmeldedaten $anmeldedaten,
        private readonly CompleteAuthentication $completeAuthentication,
        private readonly RegistrierungsKonten $registrierungsKonten,
    ) {}

    /**
     * @param  array<string, mixed>  $zugangsdaten
     * @return AuthenticationNextStep|null null bei falschen Zugangsdaten
     *
     * @throws EmailNochNichtBestaetigtException Zugangsdaten richtig, Bestätigung der Registrierung offen
     */
    public function handle(#[\SensitiveParameter] array $zugangsdaten, bool $angemeldetBleiben): ?AuthenticationNextStep
    {
        $nutzer = $this->anmeldedaten->pruefen($zugangsdaten, $angemeldetBleiben);

        if ($nutzer === null) {
            return null;
        }

        if ($this->registrierungsKonten->bestaetigungOffen($nutzer)) {
            throw new EmailNochNichtBestaetigtException;
        }

        return $this->completeAuthentication->handle($nutzer, $angemeldetBleiben);
    }
}
