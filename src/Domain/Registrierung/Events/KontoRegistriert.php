<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Registrierung\Events;

/**
 * Ein Konto ist über die Selbstregistrierung entstanden (nicht bei doppelter E-Mail).
 * Bewusst nicht `Illuminate\Auth\Events\Registered`: daran hängt Laravel die eigene Bestätigungsmail.
 */
final readonly class KontoRegistriert
{
    public function __construct(
        public int|string $nutzerId,
        public string $email,
    ) {}
}
