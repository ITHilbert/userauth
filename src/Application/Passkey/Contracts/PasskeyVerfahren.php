<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey\Contracts;

use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyAbgelehntException;
use ITHilbert\UserAuth\Application\Passkey\NeuerPasskey;
use ITHilbert\UserAuth\Application\Passkey\PasskeyBenutzer;
use ITHilbert\UserAuth\Application\Passkey\PasskeyOptionen;
use ITHilbert\UserAuth\Application\Passkey\PasskeyPartei;

/**
 * Port: WebAuthn-Verfahren (Optionen erzeugen, Antworten des Browsers kryptografisch prüfen). Challenge,
 * Benutzerverifikation (`required`) und erlaubte Algorithmen legt der Adapter fest; die Zustände und
 * Anmeldeinformationen sind für die Anwendung undurchsichtige Texte.
 */
interface PasskeyVerfahren
{
    /**
     * Optionen für `navigator.credentials.get` (discoverable: leere `allowCredentials`).
     */
    public function anmeldeOptionen(PasskeyPartei $partei): PasskeyOptionen;

    /**
     * Optionen für `navigator.credentials.create`.
     *
     * @param  list<string>  $ausgeschlosseneKennungen  base64url-Kennungen der schon eingetragenen Passkeys
     */
    public function registrierungsOptionen(PasskeyPartei $partei, PasskeyBenutzer $benutzer, array $ausgeschlosseneKennungen): PasskeyOptionen;

    /**
     * Kennung (base64url) der Anmeldeinformation aus der Antwort des Browsers, ohne Prüfung.
     *
     * @param  array<mixed>  $antwort
     *
     * @throws PasskeyAbgelehntException Antwort unvollständig oder ungültig
     */
    public function kennungDerAnmeldung(array $antwort): string;

    /**
     * Prüft die Assertion gegen Zustand und gespeicherte Anmeldeinformation; liefert die fortgeschriebene
     * Anmeldeinformation (neuer Signaturzähler).
     *
     * @param  array<mixed>  $antwort
     *
     * @throws PasskeyAbgelehntException
     */
    public function anmeldungPruefen(array $antwort, string $zustand, string $quelle, PasskeyPartei $partei): string;

    /**
     * Prüft die Registrierungsantwort gegen den Zustand.
     *
     * @param  array<mixed>  $antwort
     *
     * @throws PasskeyAbgelehntException
     */
    public function registrierungPruefen(array $antwort, string $zustand, PasskeyPartei $partei): NeuerPasskey;
}
