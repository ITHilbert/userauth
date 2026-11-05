<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Registrierung\Contracts;

use ITHilbert\UserAuth\Domain\Zugriff\Rolle;

/**
 * Port: Rolle zu einem Rollen-Schlüssel (`roles.role`); null, wenn es keine gültige, nicht gelöschte gibt.
 */
interface RegistrierungsRollen
{
    public function rolleZumSchluessel(string $schluessel): ?Rolle;
}
