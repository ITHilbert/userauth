<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\SocialLogin;

use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\DomainNotAllowedException;

final class AllowedDomainPolicy
{
    /**
     * @param  list<string>  $allowedDomains  leer = alle Domains erlaubt
     *
     * @throws DomainNotAllowedException
     */
    public function assertAllowed(SocialIdentity $identity, array $allowedDomains): void
    {
        $allowed = array_map(strtolower(...), $allowedDomains);

        if ($allowed === []) {
            return;
        }

        $hosted = $identity->hostedDomain === null ? null : strtolower($identity->hostedDomain);

        if (! in_array($identity->emailDomain(), $allowed, true)
            || ($hosted !== null && ! in_array($hosted, $allowed, true))) {
            throw new DomainNotAllowedException;
        }
    }
}
