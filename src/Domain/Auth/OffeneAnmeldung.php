<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Auth;

use DateTimeImmutable;

/**
 * Anmeldung nach bestandenem ersten Faktor (Passwort oder Social Login), die noch auf den Zwei-Faktor-Code wartet.
 * Lebt nur in der Sitzung, für die sie begonnen wurde (AK17); angemeldet wird erst nach dem Code (AK14).
 */
final readonly class OffeneAnmeldung
{
    public const CODE_GUELTIG_MINUTEN = 10;

    public const MAX_FEHLVERSUCHE = 5;

    public const ERNEUT_SENDEN_SEKUNDEN = 60;

    /**
     * @param  string  $kennung  Zufällige, eindeutige Kennung dieser offenen Anmeldung (Schlüssel für Zähler und Code-Hash)
     * @param  ?string  $ziel  Ursprünglich gewünschte Seite (AK15)
     */
    public function __construct(
        public string $kennung,
        public int|string $nutzerId,
        public bool $angemeldetBleiben,
        public ?string $ziel,
        public ?string $codeHash = null,
        public ?DateTimeImmutable $codeGueltigBis = null,
    ) {}

    public static function beginnen(int|string $nutzerId, bool $angemeldetBleiben, ?string $ziel): self
    {
        return new self(bin2hex(random_bytes(16)), $nutzerId, $angemeldetBleiben, $ziel);
    }

    /**
     * Neuer Code ersetzt den bisherigen (AK19) und gilt ab jetzt CODE_GUELTIG_MINUTEN.
     */
    public function mitCode(string $codeHash, DateTimeImmutable $jetzt): self
    {
        return new self(
            $this->kennung,
            $this->nutzerId,
            $this->angemeldetBleiben,
            $this->ziel,
            $codeHash,
            $jetzt->modify('+'.self::CODE_GUELTIG_MINUTEN.' minutes'),
        );
    }

    public function codeIstAbgelaufen(DateTimeImmutable $jetzt): bool
    {
        return $this->codeGueltigBis === null || $jetzt > $this->codeGueltigBis;
    }
}
