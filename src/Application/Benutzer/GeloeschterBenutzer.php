<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Benutzer;

/**
 * Weich gelöschter Benutzer, wie ihn der Port `GeloeschteBenutzer` liefert.
 */
final readonly class GeloeschterBenutzer
{
    public function __construct(
        public int|string $id,
        public string $email,
        public ?string $bild,
    ) {}
}
