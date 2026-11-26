<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

/**
 * Optionen für den Browser (JSON-Form von WebAuthn) und derselbe Inhalt als Zustand für die spätere Prüfung.
 */
final readonly class PasskeyOptionen
{
    /**
     * @param  array<string, mixed>  $optionen
     */
    public function __construct(
        public array $optionen,
        public string $zustand,
    ) {}
}
