<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

enum AuthenticationNextStep
{
    case TwoFactorRequired;
    case Done;

    /** 2FA verlangt, aber der Code ließ sich nicht versenden; nicht angemeldet, keine offene Anmeldung (AK14b). */
    case CodeNichtVersendet;
}
