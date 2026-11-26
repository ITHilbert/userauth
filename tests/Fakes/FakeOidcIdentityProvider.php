<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Fakes;

use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcIdentityProvider;
use ITHilbert\UserAuth\Application\Oidc\Exceptions\OidcException;
use ITHilbert\UserAuth\Application\Oidc\OidcIdentity;

final class FakeOidcIdentityProvider implements OidcIdentityProvider
{
    public ?string $autorisiertMitNonce = null;

    /** @var list<array{code: string, nonce: string}> */
    public array $abrufe = [];

    private ?OidcIdentity $identity = null;

    private bool $konfiguriert = true;

    private bool $ausfall = false;

    /**
     * @param  list<string>  $groups
     */
    public function returnIdentity(string $subject, string $email, ?string $name = 'Max Muster', array $groups = []): void
    {
        $this->identity = new OidcIdentity(subject: $subject, email: $email, name: $name, groups: $groups);
    }

    public function failWithException(): void
    {
        $this->ausfall = true;
    }

    public function markNotConfigured(): void
    {
        $this->konfiguriert = false;
    }

    public function isConfigured(): bool
    {
        return $this->konfiguriert;
    }

    public function authorizationUrl(string $state, string $nonce): string
    {
        if (! $this->konfiguriert) {
            throw new OidcException('Fake: technischer Detailfehler (darf nicht beim User landen).');
        }

        $this->autorisiertMitNonce = $nonce;

        return 'https://auth.example.test/authorize?'.http_build_query(['state' => $state, 'nonce' => $nonce]);
    }

    public function fetchIdentity(string $code, string $nonce): OidcIdentity
    {
        $this->abrufe[] = ['code' => $code, 'nonce' => $nonce];

        if ($this->ausfall || ! $this->konfiguriert || $this->identity === null) {
            throw new OidcException('Fake: technischer Detailfehler (darf nicht beim User landen).');
        }

        return $this->identity;
    }
}
