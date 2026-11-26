<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Oidc;

use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcIdentityProvider;
use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcVorgaenge;
use ITHilbert\UserAuth\Application\Oidc\Exceptions\OidcException;
use Psr\Log\LoggerInterface;

/**
 * Anwendungsfall: OIDC-Anmeldung beginnen – neuer state und nonce in der Sitzung, Ziel ist der Anbieter.
 */
final class StartOidcLogin
{
    public function __construct(
        private readonly OidcVerfuegbarkeit $verfuegbarkeit,
        private readonly OidcIdentityProvider $provider,
        private readonly OidcVorgaenge $vorgaenge,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return string Autorisierungs-URL des Anbieters
     *
     * @throws OidcException
     */
    public function handle(): string
    {
        try {
            if (! $this->verfuegbarkeit->verfuegbar()) {
                throw new OidcException('OIDC ist nicht vollständig konfiguriert.');
            }

            $vorgang = OidcVorgang::neu();
            $url = $this->provider->authorizationUrl($vorgang->state, $vorgang->nonce);
            $this->vorgaenge->merken($vorgang);

            return $url;
        } catch (OidcException $e) {
            $this->logger->warning('OIDC-Anmeldung: Start fehlgeschlagen.', ['fehlerart' => $e->getMessage()]);

            throw $e;
        }
    }
}
