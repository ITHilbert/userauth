<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\SocialLogin\Exceptions;

final class InvalidLinkTokenException extends SocialLoginException
{
    public function __construct()
    {
        parent::__construct((string) __('userauth::social.errors.invalid_token'));
    }
}
