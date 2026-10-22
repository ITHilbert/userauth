<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\SocialLogin\Exceptions;

final class AccountDisabledException extends SocialLoginException
{
    public function __construct()
    {
        parent::__construct((string) __('userauth::social.errors.account_disabled'));
    }
}
