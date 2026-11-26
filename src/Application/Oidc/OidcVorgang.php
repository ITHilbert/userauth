<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Oidc;

final readonly class OidcVorgang
{
    public function __construct(
        public string $state,
        public string $nonce,
    ) {}

    public static function neu(): self
    {
        return new self(bin2hex(random_bytes(32)), bin2hex(random_bytes(32)));
    }
}
