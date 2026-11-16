<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Konto, das zu einer Kennung gehört (Modus `benutzername`/`beides`): `kennung` steht im Reset-Link,
 * `email` ist die Zieladresse der Mail, `kontoId` trennt die Reset-Tokens je Konto.
 */
final readonly class ResetKonto
{
    public function __construct(
        public Authenticatable $nutzer,
        public int|string $kontoId,
        public string $email,
        public string $kennung,
    ) {}
}
