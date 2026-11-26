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
 * Anwendungsfall: Registrierungsantwort prüfen und den Passkey zum angemeldeten Benutzer speichern (AK10).
 * Die Challenge wird immer zuerst entnommen; der Benutzer kommt nie aus der Anfrage.
 */
final class PasskeyRegistrieren
{
    public const NAME_MAX = 100;

    /** Länge der Spalte `credential_id`. */
    private const KENNUNG_MAX = 255;

    public function __construct(
        private readonly PasskeyVerfahren $verfahren,
        private readonly PasskeyVorgaenge $vorgaenge,
        private readonly PasskeyAnmeldeinformationen $passkeys,
        private readonly PasskeyEinstellungen $einstellungen,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  mixed  $antwort  Antwort des Browsers (`PublicKeyCredential.toJSON()`)
     * @param  mixed  $name  Bezeichnung, leer = „Passkey“
     *
     * @throws PasskeyAbgelehntException
     */
    public function handle(int|string $userId, mixed $antwort, mixed $name): void
    {
        $vorgang = $this->vorgaenge->entnehmen(PasskeyVorgangsArt::Registrierung);
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

            $bezeichnung = $this->bezeichnung($name);
            $neu = $this->verfahren->registrierungPruefen($antwort, $vorgang->zustand, $this->einstellungen->partei());

            if (strlen($neu->kennung) > self::KENNUNG_MAX) {
                throw new PasskeyAbgelehntException('Kennung zu lang');
            }

            if ($this->passkeys->kennungVergeben($neu->kennung) || ! $this->passkeys->speichern($userId, $bezeichnung, $neu, $jetzt)) {
                throw new PasskeyAbgelehntException('Anmeldeinformation bereits eingetragen');
            }
        } catch (PasskeyAbgelehntException $e) {
            $this->logger->warning('Passkey-Registrierung abgelehnt.', ['fehlerart' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * @throws PasskeyAbgelehntException Name länger als 100 Zeichen
     */
    private function bezeichnung(mixed $name): string
    {
        $text = is_string($name) ? trim($name) : '';

        if (mb_strlen($text) > self::NAME_MAX) {
            throw new PasskeyAbgelehntException('Name zu lang');
        }

        return $text !== '' ? $text : 'Passkey';
    }
}
