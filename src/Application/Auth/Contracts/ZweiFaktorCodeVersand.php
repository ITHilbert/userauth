<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Stellt den Zwei-Faktor-Code an die gespeicherte Adresse des Nutzers zu.
 */
interface ZweiFaktorCodeVersand
{
    public function senden(Authenticatable $nutzer, #[\SensitiveParameter] string $code, int $gueltigMinuten): void;
}
