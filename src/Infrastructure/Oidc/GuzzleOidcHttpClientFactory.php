<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Oidc;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

final class GuzzleOidcHttpClientFactory implements OidcHttpClientFactory
{
    /** @var array<string, mixed> */
    private const STANDARD = [
        'timeout' => 10,
        'connect_timeout' => 5,
        'allow_redirects' => false,
    ];

    public function make(array $options): ClientInterface
    {
        return new Client([...self::STANDARD, ...$options]);
    }
}
