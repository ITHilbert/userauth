<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Oidc;

use GuzzleHttp\ClientInterface;

/**
 * Erzeugt die HTTP-Clients für die Aufrufe an den OIDC-Anbieter (Tests ersetzen die Fabrik im Container).
 */
interface OidcHttpClientFactory
{
    /**
     * @param  array<string, mixed>  $options  Guzzle-Optionen, z. B. `verify` (Pfad zum CA-Bundle)
     */
    public function make(array $options): ClientInterface;
}
