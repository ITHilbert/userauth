<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Benutzer;

/**
 * Wie die E-Mail für die Neuanlage frei wurde: war frei, Zeile wiederhergestellt oder alte Zeilen entfernt.
 */
final readonly class GeloeschteEmailAufloesung
{
    private function __construct(
        public int|string|null $reaktivierteId,
        public bool $entfernt,
    ) {}

    public static function frei(): self
    {
        return new self(null, false);
    }

    public static function reaktiviert(int|string $id): self
    {
        return new self($id, false);
    }

    public static function entfernt(): self
    {
        return new self(null, true);
    }

    public function istReaktiviert(): bool
    {
        return $this->reaktivierteId !== null;
    }
}
