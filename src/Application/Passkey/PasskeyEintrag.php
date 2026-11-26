<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use Carbon\CarbonImmutable;

/**
 * Ein Passkey in der Liste des Benutzers.
 */
final readonly class PasskeyEintrag
{
    public function __construct(
        public int $id,
        public string $name,
        public ?CarbonImmutable $angelegtAm,
        public ?CarbonImmutable $zuletztBenutzt,
    ) {}
}
