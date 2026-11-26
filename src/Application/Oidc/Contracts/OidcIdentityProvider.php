<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Oidc\Contracts;

use ITHilbert\UserAuth\Application\Oidc\Exceptions\OidcException;
use ITHilbert\UserAuth\Application\Oidc\OidcIdentity;

/**
 * Port zum OpenID-Connect-Anbieter (z. B. Authelia): Autorisierungs-URL bilden, Code tauschen, id_token prüfen.
 */
interface OidcIdentityProvider
{
    /**
     * Issuer, Client-ID und Client-Secret sind gesetzt (ob OIDC eingeschaltet ist, entscheidet OidcSettings).
     */
    public function isConfigured(): bool;

    /**
     * @throws OidcException
     */
    public function authorizationUrl(string $state, string $nonce): string;

    /**
     * Tauscht den Code, prüft das id_token gegen die erwartete `$nonce` und ergänzt die Claims aus dem Userinfo.
     *
     * @throws OidcException
     */
    public function fetchIdentity(string $code, string $nonce): OidcIdentity;
}
