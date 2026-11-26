<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

/**
 * Begonnener Passkey-Vorgang zwischen Optionen und Antwort des Browsers: die Optionen samt Challenge (für den
 * Adapter undurchsichtig), der Zeitpunkt und bei der Registrierung der Benutzer, für den die Optionen gelten.
 */
final readonly class PasskeyVorgang
{
    /** Gültigkeit der Challenge in Sekunden (AK6). */
    public const GUELTIG_SEKUNDEN = 300;

    public function __construct(
        public string $zustand,
        public int $erstelltAm,
        public ?string $benutzer = null,
    ) {}

    public function abgelaufen(int $jetzt): bool
    {
        return $jetzt - $this->erstelltAm > self::GUELTIG_SEKUNDEN;
    }
}
