<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

/**
 * Benutzer in den Registrierungsoptionen: stabile Benutzer-Handle (Bytes, ohne E-Mail), Konto- und Anzeigename.
 */
final readonly class PasskeyBenutzer
{
    public function __construct(
        public string $handle,
        public string $kontoName,
        public string $anzeigeName,
    ) {}
}
