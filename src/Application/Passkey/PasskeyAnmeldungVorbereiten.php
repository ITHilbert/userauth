<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use Carbon\CarbonImmutable;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVerfahren;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVorgaenge;

/**
 * Anwendungsfall: Anmeldeoptionen für einen Gast erzeugen; die Challenge ersetzt eine ältere in der Sitzung (AK3).
 */
final class PasskeyAnmeldungVorbereiten
{
    public function __construct(
        private readonly PasskeyVerfahren $verfahren,
        private readonly PasskeyVorgaenge $vorgaenge,
        private readonly PasskeyEinstellungen $einstellungen,
    ) {}

    /**
     * @return array<string, mixed> Optionen für `navigator.credentials.get`
     */
    public function handle(): array
    {
        $optionen = $this->verfahren->anmeldeOptionen($this->einstellungen->partei());
        $this->vorgaenge->merken(PasskeyVorgangsArt::Anmeldung, new PasskeyVorgang($optionen->zustand, CarbonImmutable::now()->getTimestamp()));

        return $optionen->optionen;
    }
}
