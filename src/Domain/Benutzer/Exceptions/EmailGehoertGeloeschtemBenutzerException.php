<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Benutzer\Exceptions;

use RuntimeException;

/**
 * Die E-Mail gehört einem gelöschten Benutzer und die Option lautet `sperren`.
 */
final class EmailGehoertGeloeschtemBenutzerException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The e-mail address belongs to a deleted user.');
    }
}
