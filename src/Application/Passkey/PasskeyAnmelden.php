<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use Carbon\CarbonImmutable;
use ITHilbert\UserAuth\Application\Auth\CompleteAuthentication;
use ITHilbert\UserAuth\Application\Auth\Contracts\Sitzungsanmeldung;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyAnmeldeinformationen;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVerfahren;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVorgaenge;
use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyAbgelehntException;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\RegistrierungsKonten;
use Psr\Log\LoggerInterface;

/**
 * Anwendungsfall: Assertion des Browsers prüfen und den Besitzer des Passkeys anmelden (AK4 bis AK7, AK21).
 * Die Challenge wird immer zuerst entnommen, damit jeder Versuch sie verbraucht, auch bei kaputter Antwort.
 * Der Passkey verlangt Benutzerverifikation; ein zweiter Faktor entfällt (AK5).
 */
final class PasskeyAnmelden
{
    public function __construct(
        private readonly PasskeyVerfahren $verfahren,
        private readonly PasskeyVorgaenge $vorgaenge,
        private readonly PasskeyAnmeldeinformationen $passkeys,
        private readonly PasskeyEinstellungen $einstellungen,
        private readonly Sitzungsanmeldung $sitzung,
        private readonly RegistrierungsKonten $registrierungsKonten,
        private readonly CompleteAuthentication $completeAuthentication,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  mixed  $antwort  Antwort des Browsers (`PublicKeyCredential.toJSON()`)
     *
     * @throws PasskeyAbgelehntException
     */
    public function handle(mixed $antwort): void
    {
        $vorgang = $this->vorgaenge->entnehmen(PasskeyVorgangsArt::Anmeldung);
        $jetzt = CarbonImmutable::now();

        try {
            if ($vorgang === null) {
                throw new PasskeyAbgelehntException('Challenge fehlt');
            }

            if ($vorgang->abgelaufen($jetzt->getTimestamp())) {
                throw new PasskeyAbgelehntException('Challenge abgelaufen');
            }

            if (! is_array($antwort) || $antwort === []) {
                throw new PasskeyAbgelehntException('Antwort fehlt oder ist kein JSON-Objekt');
            }

            $passkey = $this->passkeys->zurKennung($this->verfahren->kennungDerAnmeldung($antwort))
                ?? throw new PasskeyAbgelehntException('Anmeldeinformation unbekannt');

            $quelle = $this->verfahren->anmeldungPruefen($antwort, $vorgang->zustand, $passkey->quelle, $this->einstellungen->partei());

            $nutzer = $this->sitzung->nutzer($passkey->userId)
                ?? throw new PasskeyAbgelehntException('Benutzer gelöscht oder deaktiviert');

            if ($this->registrierungsKonten->bestaetigungOffen($nutzer)) {
                throw new PasskeyAbgelehntException('E-Mail-Bestätigung der Registrierung offen');
            }

            $this->passkeys->nachAnmeldung($passkey->id, $quelle, $jetzt);
            $this->completeAuthentication->ohneZweitenFaktor($nutzer);
        } catch (PasskeyAbgelehntException $e) {
            $this->logger->warning('Passkey-Anmeldung abgelehnt.', ['fehlerart' => $e->getMessage()]);

            throw $e;
        }
    }
}
