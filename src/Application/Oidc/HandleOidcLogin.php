<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Oidc;

use ITHilbert\UserAuth\Application\Auth\CompleteAuthentication;
use ITHilbert\UserAuth\Application\Auth\Contracts\Sitzungsanmeldung;
use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcIdentityProvider;
use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcVorgaenge;
use ITHilbert\UserAuth\Application\Oidc\Exceptions\OidcException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\AccountDisabledException;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialLoginException;
use Psr\Log\LoggerInterface;

/**
 * Anwendungsfall: Callback des OIDC-Anbieters für einen Gast – state prüfen (einmal verwendbar), Code tauschen,
 * Benutzer zuordnen und anmelden. Der Anbieter ist der eigene Anmeldedienst; ein zweiter Faktor entfällt (AK13).
 */
final class HandleOidcLogin
{
    public function __construct(
        private readonly OidcVerfuegbarkeit $verfuegbarkeit,
        private readonly OidcVorgaenge $vorgaenge,
        private readonly OidcIdentityProvider $provider,
        private readonly OidcBenutzerZuordnen $zuordnen,
        private readonly Sitzungsanmeldung $sitzung,
        private readonly CompleteAuthentication $completeAuthentication,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws OidcException technischer Fehler (state, Anbieter, Token) – Benutzer sehen eine allgemeine Meldung
     * @throws SocialLoginException fachliche Ablehnung (Konto fehlt, deaktiviert, nicht eindeutig …)
     */
    public function handle(?string $state, ?string $code, ?string $error): void
    {
        // Immer zuerst entnehmen: state und nonce gelten genau für einen Callback, auch wenn er scheitert
        $vorgang = $this->vorgaenge->entnehmen();

        try {
            if (! $this->verfuegbarkeit->verfuegbar()) {
                throw new OidcException('OIDC ist nicht vollständig konfiguriert.');
            }

            if ($vorgang === null || $state === null || $state === '' || ! hash_equals($vorgang->state, $state)) {
                throw new OidcException('state fehlt oder passt nicht.');
            }

            if ($error !== null && $error !== '') {
                throw new OidcException('Anbieter meldet einen Fehler'.$this->fehlercode($error).'.');
            }

            if ($code === null || $code === '') {
                throw new OidcException('code fehlt.');
            }

            $identity = $this->provider->fetchIdentity($code, $vorgang->nonce);
            $user = $this->zuordnen->handle($identity);
            $authenticatable = $this->sitzung->nutzer($user->id) ?? throw new AccountDisabledException;

            $this->completeAuthentication->ohneZweitenFaktor($authenticatable);
        } catch (OidcException $e) {
            $this->logger->warning('OIDC-Anmeldung abgelehnt.', ['fehlerart' => $e->getMessage()]);

            throw $e;
        } catch (SocialLoginException $e) {
            $this->logger->info('OIDC-Anmeldung abgelehnt.', ['fehlerart' => $e::class]);

            throw $e;
        }
    }

    /**
     * Nur ein harmloser Fehlercode nach RFC 6749 (z. B. access_denied) gelangt ins Log, nie freier Text.
     */
    private function fehlercode(string $error): string
    {
        return preg_match('/^[a-z_]{1,64}$/', $error) === 1 ? " ({$error})" : '';
    }
}
