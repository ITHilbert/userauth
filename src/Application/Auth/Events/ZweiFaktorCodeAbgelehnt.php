<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Ein eingegebener Zwei-Faktor-Code wurde abgelehnt (falsch oder abgelaufen). Eigenes Audit-Ereignis (AK22),
 * bewusst kein `Failed` des Guards: Das Passwort war richtig.
 */
final readonly class ZweiFaktorCodeAbgelehnt
{
    public function __construct(public Authenticatable $user) {}
}
