<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Oidc;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Routing\UrlGenerator;
use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcIdentityProvider;
use ITHilbert\UserAuth\Application\Oidc\Exceptions\OidcException;
use ITHilbert\UserAuth\Application\Oidc\OidcIdentity;
use ITHilbert\UserAuth\Application\Oidc\OidcSettings;

/**
 * Adapter zum OIDC-Anbieter (Authorization-Code-Flow, `client_secret_post`). Discovery bei jedem Vorgang (kein
 * Cache), alle Aufrufe mit `verify` = CA-Bundle, falls gesetzt. Fehler des Anbieters werden zu OidcException;
 * deren Meldung nennt nur die Fehlerart – Guzzle-Meldungen (mit Antwortinhalt) werden bewusst nicht übernommen.
 */
final class GuzzleOidcIdentityProvider implements OidcIdentityProvider
{
    private const SCOPE = 'openid profile email groups';

    private const ENDPUNKTE = ['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'userinfo_endpoint'];

    public function __construct(
        private readonly OidcSettings $settings,
        private readonly OidcHttpClientFactory $clients,
        private readonly IdTokenPruefung $tokenPruefung,
        private readonly UrlGenerator $urls,
    ) {}

    public function isConfigured(): bool
    {
        return $this->settings->hasCredentials();
    }

    public function authorizationUrl(string $state, string $nonce): string
    {
        $endpunkt = $this->discovery()['authorization_endpoint'];

        return $endpunkt.(str_contains($endpunkt, '?') ? '&' : '?').http_build_query([
            'response_type' => 'code',
            'client_id' => $this->settings->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => self::SCOPE,
            'state' => $state,
            'nonce' => $nonce,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function fetchIdentity(string $code, string $nonce): OidcIdentity
    {
        $endpunkte = $this->discovery();

        $tokens = $this->json('Token', 'POST', $endpunkte['token_endpoint'], ['form_params' => [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'client_id' => $this->settings->clientId(),
            'client_secret' => $this->settings->clientSecret(),
        ]]);

        $idToken = $tokens['id_token'] ?? null;
        $accessToken = $tokens['access_token'] ?? null;

        if (! is_string($idToken) || $idToken === '' || ! is_string($accessToken) || $accessToken === '') {
            throw new OidcException('Token-Antwort ohne id_token oder access_token.');
        }

        $jwks = $this->json('JWKS', 'GET', $endpunkte['jwks_uri']);
        $claims = $this->tokenPruefung->pruefen($idToken, $jwks, $this->settings->issuer(), $this->settings->clientId(), $nonce);

        $userinfo = $this->json('Userinfo', 'GET', $endpunkte['userinfo_endpoint'], [
            'headers' => ['Authorization' => 'Bearer '.$accessToken],
        ]);

        if (($userinfo['sub'] ?? null) !== $claims['sub']) {
            throw new OidcException('Userinfo: sub weicht vom id_token ab.');
        }

        // Userinfo ergänzt die Claims des id_token (null-Werte zählen nicht), `sub` bleibt der des id_token
        $ergaenzt = array_filter($userinfo, static fn (mixed $wert): bool => $wert !== null);

        return $this->identitaet([...$claims, ...$ergaenzt, 'sub' => $claims['sub']]);
    }

    /**
     * @param  array<string, mixed>  $claims
     *
     * @throws OidcException
     */
    private function identitaet(array $claims): OidcIdentity
    {
        $email = $claims['email'] ?? null;

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new OidcException('Claims ohne gültige E-Mail.');
        }

        // Fehlt email_verified, gilt die E-Mail als bestätigt (eigener Anmeldedienst); nur ausdrücklich false lehnt ab
        if (array_key_exists('email_verified', $claims)
            && filter_var($claims['email_verified'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false) {
            throw new OidcException('E-Mail beim Anbieter nicht bestätigt.');
        }

        $groups = $claims['groups'] ?? null;

        return new OidcIdentity(
            subject: (string) $claims['sub'],
            email: $email,
            name: $this->anzeigename($claims),
            groups: is_array($groups) ? array_values(array_filter($groups, is_string(...))) : [],
        );
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function anzeigename(array $claims): ?string
    {
        foreach (['name', 'preferred_username'] as $claim) {
            if (is_string($claims[$claim] ?? null) && trim($claims[$claim]) !== '') {
                return trim($claims[$claim]);
            }
        }

        return null;
    }

    /**
     * @return array<string, string> die vier Endpunkte aus `<issuer>/.well-known/openid-configuration`
     *
     * @throws OidcException
     */
    private function discovery(): array
    {
        $antwort = $this->json('Discovery', 'GET', rtrim($this->settings->issuer(), '/').'/.well-known/openid-configuration');
        $endpunkte = [];

        foreach (self::ENDPUNKTE as $schluessel) {
            if (! is_string($antwort[$schluessel] ?? null) || $antwort[$schluessel] === '') {
                throw new OidcException("Discovery ohne {$schluessel}.");
            }

            $endpunkte[$schluessel] = $antwort[$schluessel];
        }

        return $endpunkte;
    }

    /**
     * @param  array<string, mixed>  $optionen
     * @return array<string, mixed>
     *
     * @throws OidcException
     */
    private function json(string $schritt, string $methode, string $url, array $optionen = []): array
    {
        try {
            $antwort = $this->clients->make($this->clientOptionen())->request($methode, $url, [
                ...$optionen,
                'headers' => ['Accept' => 'application/json', ...($optionen['headers'] ?? [])],
            ]);
            $daten = json_decode((string) $antwort->getBody(), true, 64, JSON_THROW_ON_ERROR);
        } catch (GuzzleException|\InvalidArgumentException|\JsonException $e) {
            // InvalidArgumentException: z. B. CA-Bundle nicht vorhanden (Randfall 3)
            throw new OidcException("{$schritt}: Anbieter nicht erreichbar oder Antwort ungültig (".$e::class.').');
        }

        if (! is_array($daten)) {
            throw new OidcException("{$schritt}: Antwort ist kein JSON-Objekt.");
        }

        return $daten;
    }

    /**
     * @return array<string, mixed>
     */
    private function clientOptionen(): array
    {
        $caBundle = $this->settings->caBundle();

        return $caBundle !== '' ? ['verify' => $caBundle] : [];
    }

    private function redirectUri(): string
    {
        return $this->urls->route('userauth.oidc.callback');
    }
}
