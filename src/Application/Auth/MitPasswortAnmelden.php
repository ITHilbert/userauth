<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use ITHilbert\UserAuth\Application\Auth\Contracts\Anmeldedaten;
use ITHilbert\UserAuth\Application\Auth\Contracts\KontoZurKennung;
use ITHilbert\UserAuth\Application\Passkey\PasswortLoginSperre;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\RegistrierungsKonten;
use ITHilbert\UserAuth\Domain\Registrierung\Exceptions\EmailNochNichtBestaetigtException;

/**
 * Anmeldung mit Kennung (je `userauth.anmeldung_per`: E-Mail, Benutzername oder beides) und Passwort.
 * Im Modus `email` prüft der Guard die Zugangsdaten wie bisher; sonst wird zuerst genau ein Konto zur Kennung
 * gesucht (mehrdeutig oder unbekannt: kein Passwortvergleich). Die Zugangsdaten werden nur geprüft; angemeldet wird in
 * CompleteAuthentication – bei aktiver 2FA erst nach dem Code (AK14).
 * Konten aus der Selbstregistrierung mit offener Bestätigung werden nicht angemeldet (auch kein 2FA-Code);
 * andere Konten mit leerem `email_verified_at` (Admin, Seeder, Social Login) melden sich an wie bisher.
 * Sperrt `userauth.passwort_login` das Passwort für den Benutzer, gilt die Anmeldung wie mit falschen Zugangsdaten
 * (kein Hinweis, dass das Passwort stimmt; passkey-login AK12, AK13).
 */
final class MitPasswortAnmelden
{
    public function __construct(
        private readonly Anmeldedaten $anmeldedaten,
        private readonly CompleteAuthentication $completeAuthentication,
        private readonly RegistrierungsKonten $registrierungsKonten,
        private readonly AnmeldeEinstellungen $anmeldeEinstellungen,
        private readonly KontoZurKennung $kontoZurKennung,
        private readonly PasswortLoginSperre $passwortLoginSperre,
    ) {}

    /**
     * @param  array<string, mixed>  $zugangsdaten
     * @return AuthenticationNextStep|null null bei falschen Zugangsdaten
     *
     * @throws EmailNochNichtBestaetigtException Zugangsdaten richtig, Bestätigung der Registrierung offen
     */
    public function handle(#[\SensitiveParameter] array $zugangsdaten, bool $angemeldetBleiben): ?AuthenticationNextStep
    {
        $modus = $this->anmeldeEinstellungen->modus();

        if ($modus->brauchtBenutzername()) {
            $kennung = $zugangsdaten[$modus->feld()] ?? '';
            $konto = $this->kontoZurKennung->finden($modus, is_string($kennung) ? $kennung : '');
            $nutzer = $this->anmeldedaten->kontoPruefen($konto, $zugangsdaten, $angemeldetBleiben);
        } else {
            $nutzer = $this->anmeldedaten->pruefen($zugangsdaten, $angemeldetBleiben);
        }

        if ($nutzer === null || $this->passwortLoginSperre->gesperrt($nutzer)) {
            return null;
        }

        if ($this->registrierungsKonten->bestaetigungOffen($nutzer)) {
            throw new EmailNochNichtBestaetigtException;
        }

        return $this->completeAuthentication->handle($nutzer, $angemeldetBleiben);
    }
}
