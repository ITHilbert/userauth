<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Fakes;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use ITHilbert\UserAuth\Infrastructure\Oidc\OidcHttpClientFactory;
use OpenSSLAsymmetricKey;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Fake-Anbieter hinter der Client-Fabrik: Guzzle-Clients mit eigenem Handler statt Netz. Beantwortet Discovery, Token,
 * JWKS und Userinfo, hält die gesendeten Requests (mit Guzzle-Option `verify`) fest und signiert id_tokens mit
 * einem selbst erzeugten RSA-Schlüsselpaar.
 */
final class FakeOidcHttpClientFactory implements OidcHttpClientFactory
{
    public const ISSUER = 'https://auth.example.test';

    public const CLIENT_ID = 'client-app';

    public const KID = 'kid-1';

    /** @var array<string, string> Schlüssel => URL */
    public const URLS = [
        'discovery' => self::ISSUER.'/.well-known/openid-configuration',
        'authorization' => self::ISSUER.'/api/oidc/authorization',
        'token' => self::ISSUER.'/api/oidc/token',
        'jwks' => self::ISSUER.'/jwks.json',
        'userinfo' => self::ISSUER.'/api/oidc/userinfo',
    ];

    /** @var list<array{key: string, method: string, url: string, body: string, authorization: string, verify: mixed}> */
    public array $requests = [];

    /** @var list<array<string, mixed>> */
    public array $makeOptions = [];

    /** @var array<string, mixed> */
    private array $discovery = [
        'issuer' => self::ISSUER,
        'authorization_endpoint' => self::URLS['authorization'],
        'token_endpoint' => self::URLS['token'],
        'jwks_uri' => self::URLS['jwks'],
        'userinfo_endpoint' => self::URLS['userinfo'],
    ];

    /** @var array<string, mixed> */
    private array $claims = [];

    /** @var array<string, mixed> */
    private array $userinfo = [
        'sub' => 'sub-1',
        'email' => 'max@firma.de',
        'name' => 'Max Muster',
        'groups' => [],
    ];

    /** @var array<string, ResponseInterface|Throwable> */
    private array $antworten = [];

    private ?string $idToken = null;

    private ?string $nonce = 'nonce-1';

    private OpenSSLAsymmetricKey $schluessel;

    private OpenSSLAsymmetricKey $fremderSchluessel;

    public function __construct()
    {
        $this->schluessel = $this->neuerSchluessel();
        $this->fremderSchluessel = $this->neuerSchluessel();
    }

    public function make(array $options): ClientInterface
    {
        $this->makeOptions[] = $options;

        return new Client([...$options, 'handler' => HandlerStack::create($this->handler(...))]);
    }

    public function nonce(?string $nonce): self
    {
        $this->nonce = $nonce;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $claims  null entfernt den Claim aus dem id_token
     */
    public function claims(array $claims): self
    {
        $this->claims = [...$this->claims, ...$claims];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $claims  null entfernt den Claim aus dem Userinfo
     */
    public function userinfo(array $claims): self
    {
        $this->userinfo = [...$this->userinfo, ...$claims];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $felder  null entfernt das Feld aus der Discovery-Antwort
     */
    public function discovery(array $felder): self
    {
        $this->discovery = [...$this->discovery, ...$felder];

        return $this;
    }

    public function antwort(string $key, ResponseInterface|Throwable $antwort): self
    {
        $this->antworten[$key] = $antwort;

        return $this;
    }

    public function idToken(string $jwt): self
    {
        $this->idToken = $jwt;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $claims  Überschreibungen der Standard-Claims (null entfernt)
     */
    public function signiert(array $claims = [], string $kid = self::KID, bool $fremderSchluessel = false): string
    {
        return JWT::encode(
            $this->claimsFuerToken($claims),
            $fremderSchluessel ? $this->fremderSchluessel : $this->schluessel,
            'RS256',
            $kid,
        );
    }

    /**
     * Token ohne Signatur und mit Algorithmus `none`.
     *
     * @param  array<string, mixed>  $claims
     */
    public function ohneSignatur(array $claims = []): string
    {
        $teile = [
            $this->b64(json_encode(['alg' => 'none', 'typ' => 'JWT', 'kid' => self::KID], JSON_THROW_ON_ERROR)),
            $this->b64(json_encode($this->claimsFuerToken($claims), JSON_THROW_ON_ERROR)),
            '',
        ];

        return implode('.', $teile);
    }

    /**
     * HS256-Token, das mit dem öffentlichen Schlüssel als Geheimnis signiert ist (Algorithmus-Verwechslung).
     *
     * @param  array<string, mixed>  $claims
     */
    public function hs256MitOeffentlichemSchluessel(array $claims = []): string
    {
        $pem = openssl_pkey_get_details($this->schluessel)['key'];

        return JWT::encode($this->claimsFuerToken($claims), $pem, 'HS256', self::KID);
    }

    /**
     * @return list<array{key: string, method: string, url: string, body: string, authorization: string, verify: mixed}>
     */
    public function requestsTo(string $key): array
    {
        return array_values(array_filter($this->requests, fn (array $r): bool => $r['key'] === $key));
    }

    /**
     * @return array<string, string>
     */
    public function tokenForm(): array
    {
        parse_str($this->requestsTo('token')[0]['body'], $form);

        return $form;
    }

    private function handler(RequestInterface $request, array $options)
    {
        $url = (string) $request->getUri()->withQuery('');
        $key = array_search($url, self::URLS, true);
        $key = $key === false ? 'unbekannt' : $key;
        $verify = $options['verify'] ?? null;

        $this->requests[] = [
            'key' => $key,
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
            'body' => (string) $request->getBody(),
            'authorization' => $request->getHeaderLine('Authorization'),
            'verify' => $verify,
        ];

        // Wie Guzzle selbst: ein nicht vorhandenes CA-Bundle wirft beim Request
        if (is_string($verify) && ! is_file($verify)) {
            throw new InvalidArgumentException("SSL CA bundle not found: {$verify}");
        }

        $antwort = $this->antworten[$key] ?? $this->standardAntwort($key);

        if ($antwort instanceof Throwable) {
            return Create::rejectionFor($antwort);
        }

        return Create::promiseFor($antwort);
    }

    private function standardAntwort(string $key): Response
    {
        $inhalt = match ($key) {
            'discovery' => array_filter($this->discovery, fn (mixed $wert): bool => $wert !== null),
            'token' => [
                'access_token' => 'AT-geheim-1',
                'token_type' => 'Bearer',
                'id_token' => $this->idToken ?? $this->signiert(),
            ],
            'jwks' => ['keys' => [$this->jwk()]],
            'userinfo' => array_filter($this->userinfo, fn (mixed $wert): bool => $wert !== null),
            default => [],
        };

        return new Response($key === 'unbekannt' ? 404 : 200, ['Content-Type' => 'application/json'], json_encode($inhalt, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $ueberschreibung
     * @return array<string, mixed>
     */
    private function claimsFuerToken(array $ueberschreibung): array
    {
        $standard = [
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => 'sub-1',
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => $this->nonce,
        ];

        return array_filter(
            [...$standard, ...$this->claims, ...$ueberschreibung],
            fn (mixed $wert): bool => $wert !== null,
        );
    }

    /**
     * @return array<string, string>
     */
    private function jwk(): array
    {
        $rsa = openssl_pkey_get_details($this->schluessel)['rsa'];

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => self::KID,
            'n' => $this->b64($rsa['n']),
            'e' => $this->b64($rsa['e']),
        ];
    }

    private function neuerSchluessel(): OpenSSLAsymmetricKey
    {
        return openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    }

    private function b64(string $daten): string
    {
        return rtrim(strtr(base64_encode($daten), '+/', '-_'), '=');
    }
}
