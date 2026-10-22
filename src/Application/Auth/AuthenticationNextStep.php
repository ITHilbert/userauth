<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

enum AuthenticationNextStep
{
    case TwoFactorRequired;
    case Done;
}
