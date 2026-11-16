<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use Illuminate\Contracts\Config\Repository;
use ITHilbert\UserAuth\Domain\Auth\AnmeldeModus;

/**
 * Liest `userauth.anmeldung_per` je Aufruf (keine Zwischenspeicherung: Hosts und Tests ändern die Config zur Laufzeit).
 */
final class AnmeldeEinstellungen
{
    public function __construct(private readonly Repository $config) {}

    public function modus(): AnmeldeModus
    {
        return AnmeldeModus::ausWert($this->config->get('userauth.anmeldung_per', AnmeldeModus::Email->value));
    }
}
