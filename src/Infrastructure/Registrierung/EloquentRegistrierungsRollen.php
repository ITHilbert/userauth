<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Registrierung;

use ITHilbert\UserAuth\Application\Registrierung\Contracts\RegistrierungsRollen;
use ITHilbert\UserAuth\Domain\Zugriff\Rolle;
use ITHilbert\UserAuth\Entities\Role;

final class EloquentRegistrierungsRollen implements RegistrierungsRollen
{
    public function rolleZumSchluessel(string $schluessel): ?Rolle
    {
        return Role::query()->where('role', $schluessel)->first()?->alsZugriffsrolle();
    }
}
