<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Passwort;

use Illuminate\Contracts\Auth\CanResetPassword;

/**
 * Schlüssel eines Reset-Tokens für Laravels Token-Repository: `konto:<id>` statt der E-Mail, damit Konten mit
 * gemeinsamer E-Mail getrennte Tokens haben. Das Präfix kann mit keiner gültigen E-Mail (Modus `email`) kollidieren.
 */
final readonly class KontoResetSchluessel implements CanResetPassword
{
    public function __construct(private int|string $kontoId) {}

    public function getEmailForPasswordReset(): string
    {
        return 'konto:'.$this->kontoId;
    }

    /**
     * @throws \LogicException Den Versand übernimmt `ResetLinkVersand`, nie das Token-Repository.
     */
    public function sendPasswordResetNotification($token): void
    {
        throw new \LogicException('KontoResetSchluessel is only a token key and cannot send notifications.');
    }
}
