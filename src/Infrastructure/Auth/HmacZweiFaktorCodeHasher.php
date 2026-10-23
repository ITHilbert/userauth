<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Auth;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Encryption\MissingAppKeyException;
use ITHilbert\UserAuth\Application\Auth\Contracts\ZweiFaktorCodeHasher;

/**
 * HMAC-SHA256 mit dem App-Key: Ohne den Schlüssel lässt sich der sechsstellige Code nicht aus dem Hash
 * zurückrechnen (ein ungesalzener Hash wäre bei nur 10^6 Möglichkeiten sofort umkehrbar).
 */
final class HmacZweiFaktorCodeHasher implements ZweiFaktorCodeHasher
{
    public function __construct(private readonly Repository $config) {}

    public function hash(#[\SensitiveParameter] string $code, string $kennung): string
    {
        return hash_hmac('sha256', $kennung.'|'.$code, $this->schluessel());
    }

    public function stimmt(#[\SensitiveParameter] string $code, string $kennung, string $hash): bool
    {
        return hash_equals($hash, $this->hash($code, $kennung));
    }

    /**
     * @throws MissingAppKeyException
     */
    private function schluessel(): string
    {
        $schluessel = $this->config->get('app.key');

        if (! is_string($schluessel) || $schluessel === '') {
            throw new MissingAppKeyException;
        }

        return $schluessel;
    }
}
