<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

enum SocialLoginOutcome
{
    case LoggedIn;
    case ConfirmationRequired;
}
