<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Zugriff\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use ITHilbert\UserAuth\Domain\Zugriff\Rolle;

/**
 * Port: liefert die wirksame Rolle eines Nutzers (bei aktiven Teams die Rolle im aktuellen Team).
 */
interface RollenRepository
{
    /**
     * null, wenn der Nutzer keine gültige, vorhandene und nicht gelöschte Rolle hat.
     */
    public function aktiveRolle(Authenticatable $nutzer): ?Rolle;
}
