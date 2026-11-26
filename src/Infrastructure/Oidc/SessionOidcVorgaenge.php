<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Oidc;

use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcVorgaenge;
use ITHilbert\UserAuth\Application\Oidc\OidcVorgang;

/**
 * Legt `state` und `nonce` unter `userauth.oidc.state` und `userauth.oidc.nonce` in der Sitzung ab.
 */
final class SessionOidcVorgaenge implements OidcVorgaenge
{
    private const STATE = 'userauth.oidc.state';

    private const NONCE = 'userauth.oidc.nonce';

    public function __construct(private readonly Request $request) {}

    public function merken(OidcVorgang $vorgang): void
    {
        $this->request->session()->put([self::STATE => $vorgang->state, self::NONCE => $vorgang->nonce]);
    }

    public function entnehmen(): ?OidcVorgang
    {
        $session = $this->request->session();
        $state = $session->pull(self::STATE);
        $nonce = $session->pull(self::NONCE);

        if (! is_string($state) || $state === '' || ! is_string($nonce) || $nonce === '') {
            return null;
        }

        return new OidcVorgang($state, $nonce);
    }
}
