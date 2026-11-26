<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

/**
 * Eingetragener Passkey mit seiner Anmeldeinformation (für den Adapter undurchsichtig).
 */
final readonly class GespeicherterPasskey
{
    public function __construct(
        public int $id,
        public int|string $userId,
        public string $quelle,
    ) {}
}
