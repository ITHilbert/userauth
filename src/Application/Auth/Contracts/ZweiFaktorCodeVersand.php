<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use ITHilbert\UserAuth\Application\Auth\Exceptions\ZweiFaktorCodeVersandException;

/**
 * Stellt den Zwei-Faktor-Code an die gespeicherte Adresse des Nutzers zu.
 */
interface ZweiFaktorCodeVersand
{
    /**
     * @throws ZweiFaktorCodeVersandException wenn der Code nicht zugestellt werden konnte
     */
    public function senden(Authenticatable $nutzer, #[\SensitiveParameter] string $code, int $gueltigMinuten): void;
}
