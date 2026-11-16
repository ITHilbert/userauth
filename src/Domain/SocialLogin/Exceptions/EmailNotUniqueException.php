<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\SocialLogin\Exceptions;

/**
 * Mehrere aktive Konten teilen die E-Mail der Anbieter-Identität: Es gibt kein eindeutiges Konto zum Verknüpfen.
 * Der Text nennt keine Konten.
 */
final class EmailNotUniqueException extends SocialLoginException
{
    public function __construct()
    {
        parent::__construct((string) __('userauth::social.errors.email_not_unique'));
    }
}
