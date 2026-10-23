<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Passwort\Exceptions;

/**
 * Das neue Passwort entspricht dem aktuellen oder einem der zuletzt verwendeten (Passwort-Richtlinie).
 */
final class PasswortBereitsVerwendetException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The new password matches the current or a recently used password.');
    }
}
