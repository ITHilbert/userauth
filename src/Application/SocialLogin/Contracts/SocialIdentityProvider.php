<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin\Contracts;

use ITHilbert\UserAuth\Application\SocialLogin\Exceptions\SocialProviderUnavailableException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

interface SocialIdentityProvider
{
    /**
     * Ob der Anbieter vollständig konfiguriert ist (Zugangsdaten vorhanden).
     */
    public function isAvailable(SocialProvider $provider): bool;

    /**
     * @throws SocialProviderUnavailableException
     */
    public function authorizationUrl(SocialProvider $provider): string;

    /**
     * @throws SocialProviderUnavailableException
     */
    public function fetchIdentity(SocialProvider $provider): SocialIdentity;
}
