<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Registrierung;

final readonly class RegistriertesKonto
{
    public function __construct(
        public int|string $id,
        public string $email,
        public bool $emailBestaetigt,
    ) {}
}
