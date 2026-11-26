<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use Carbon\CarbonImmutable;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyAnmeldeinformationen;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVerfahren;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVorgaenge;
use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyAbgelehntException;
use Psr\Log\LoggerInterface;

/**
 * Anwendungsfall: Assertion eines Passkeys des angemeldeten Benutzers prüfen und die Bestätigung erteilen
 * (passkey-bestaetigung, AK6). Die Challenge wird immer zuerst entnommen, damit jeder Versuch sie verbraucht.
 * Der Benutzer wird nicht neu angemeldet.
 */
final class PasskeyBestaetigen
{
    public function __construct(
        private readonly PasskeyVerfahren $verfahren,
        private readonly PasskeyVorgaenge $vorgaenge,
        private readonly PasskeyAnmeldeinformationen $passkeys,
        private readonly PasskeyEinstellungen $einstellungen,
        private readonly PasskeyBestaetigung $bestaetigung,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  mixed  $antwort  Antwort des Browsers (`PublicKeyCredential.toJSON()`)
     *
     * @throws PasskeyAbgelehntException
     */
    public function handle(int|string $userId, mixed $antwort): void
    {
        $vorgang = $this->vorgaenge->entnehmen(PasskeyVorgangsArt::Bestaetigung);
        $jetzt = CarbonImmutable::now();

        try {
            if ($vorgang === null || $vorgang->benutzer !== (string) $userId) {
                throw new PasskeyAbgelehntException('Challenge fehlt');
            }

            if ($vorgang->abgelaufen($jetzt->getTimestamp())) {
                throw new PasskeyAbgelehntException('Challenge abgelaufen');
            }

            if (! is_array($antwort) || $antwort === []) {
                throw new PasskeyAbgelehntException('Antwort fehlt oder ist kein JSON-Objekt');
            }

            $passkey = $this->passkeys->zurKennung($this->verfahren->kennungDerAnmeldung($antwort));
            if ($passkey === null || (string) $passkey->userId !== (string) $userId) {
                throw new PasskeyAbgelehntException('Anmeldeinformation unbekannt oder eines anderen Benutzers');
            }

            $quelle = $this->verfahren->anmeldungPruefen($antwort, $vorgang->zustand, $passkey->quelle, $this->einstellungen->partei());

            $this->passkeys->nachAnmeldung($passkey->id, $quelle, $jetzt);
            $this->bestaetigung->erteilen($userId);
        } catch (PasskeyAbgelehntException $e) {
            $this->logger->warning('Passkey-Bestätigung abgelehnt.', ['fehlerart' => $e->getMessage()]);

            throw $e;
        }
    }
}
