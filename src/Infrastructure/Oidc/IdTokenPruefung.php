<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Oidc;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use ITHilbert\UserAuth\Application\Oidc\Exceptions\OidcException;

/**
 * Prüft ein id_token (AK7): Signatur nur RS256 gegen den Schlüssel der `kid` aus dem JWKS, `iss`, `aud`
 * (Text oder Liste), `exp` mit 60 s Toleranz und `nonce`. Meldungen nennen nur die Fehlerart, nie das Token.
 */
final class IdTokenPruefung
{
    private const ALGORITHMUS = 'RS256';

    private const TOLERANZ_SEKUNDEN = 60;

    /**
     * @param  array<string, mixed>  $jwks
     * @return array<string, mixed> geprüfte Claims
     *
     * @throws OidcException
     */
    public function pruefen(string $idToken, array $jwks, string $issuer, string $clientId, string $nonce): array
    {
        $claims = $this->entschluesseln($idToken, $this->schluessel($jwks));

        $iss = $claims['iss'] ?? null;
        if (! is_string($iss) || rtrim($iss, '/') !== rtrim($issuer, '/')) {
            throw new OidcException('id_token: iss passt nicht zum Issuer.');
        }

        $aud = $claims['aud'] ?? null;
        if (! (is_string($aud) && $aud === $clientId) && ! (is_array($aud) && in_array($clientId, $aud, true))) {
            throw new OidcException('id_token: aud enthält die Client-ID nicht.');
        }

        if (! is_int($claims['exp'] ?? null) && ! is_float($claims['exp'] ?? null)) {
            throw new OidcException('id_token: exp fehlt.');
        }

        $tokenNonce = $claims['nonce'] ?? null;
        if (! is_string($tokenNonce) || ! hash_equals($nonce, $tokenNonce)) {
            throw new OidcException('id_token: nonce fehlt oder passt nicht.');
        }

        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new OidcException('id_token: sub fehlt.');
        }

        return $claims;
    }

    /**
     * Nur RS256-Schlüssel; ein Token mit anderem Algorithmus (none, HS256 …) passt zu keinem Schlüssel.
     *
     * @param  array<string, mixed>  $jwks
     * @return array<string, Key>
     *
     * @throws OidcException
     */
    private function schluessel(array $jwks): array
    {
        try {
            $schluessel = JWK::parseKeySet($jwks, self::ALGORITHMUS);
        } catch (\UnexpectedValueException|\InvalidArgumentException|\DomainException) {
            throw new OidcException('JWKS ungültig.');
        }

        $rs256 = array_filter($schluessel, static fn (Key $key): bool => $key->getAlgorithm() === self::ALGORITHMUS);

        if ($rs256 === []) {
            throw new OidcException('JWKS ohne RS256-Schlüssel.');
        }

        return $rs256;
    }

    /**
     * @param  array<string, Key>  $schluessel
     * @return array<string, mixed>
     *
     * @throws OidcException
     */
    private function entschluesseln(string $idToken, array $schluessel): array
    {
        $vorher = JWT::$leeway;
        JWT::$leeway = self::TOLERANZ_SEKUNDEN;

        try {
            $payload = JWT::decode($idToken, $schluessel);
        } catch (SignatureInvalidException) {
            throw new OidcException('id_token: Signatur ungültig.');
        } catch (ExpiredException) {
            throw new OidcException('id_token: abgelaufen.');
        } catch (BeforeValidException) {
            throw new OidcException('id_token: noch nicht gültig.');
        } catch (\UnexpectedValueException|\InvalidArgumentException|\DomainException $e) {
            // Unbekannte kid, falscher Algorithmus, kein JWT: nur die Art des Fehlers, nie das Token
            throw new OidcException('id_token ungültig ('.$e::class.').');
        } finally {
            JWT::$leeway = $vorher;
        }

        try {
            $claims = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new OidcException('id_token: Claims nicht lesbar.');
        }

        if (! is_array($claims)) {
            throw new OidcException('id_token: Claims nicht lesbar.');
        }

        return $claims;
    }
}
