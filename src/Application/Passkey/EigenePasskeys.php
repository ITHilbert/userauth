<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyAnmeldeinformationen;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialAccountRepository;
use ITHilbert\UserAuth\Domain\Passkey\PasswortLoginModus;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

/**
 * Anwendungsfall: Passkeys des angemeldeten Benutzers für die Verwaltungsseite (AK8). Gewarnt wird, wenn die
 * Passwort-Anmeldung gesperrt ist (`nie`) und weder OIDC noch Social Login verknüpft ist (AK11).
 */
final class EigenePasskeys
{
    public function __construct(
        private readonly PasskeyAnmeldeinformationen $passkeys,
        private readonly SocialAccountRepository $verknuepfungen,
        private readonly PasskeyEinstellungen $einstellungen,
    ) {}

    public function handle(int|string $userId): PasskeyUebersicht
    {
        return new PasskeyUebersicht($this->passkeys->desBenutzers($userId), $this->warnung($userId));
    }

    private function warnung(int|string $userId): bool
    {
        if ($this->einstellungen->passwortLoginModus() !== PasswortLoginModus::Nie) {
            return false;
        }

        foreach (SocialProvider::cases() as $anbieter) {
            if ($this->verknuepfungen->userHasProvider($userId, $anbieter)) {
                return false;
            }
        }

        return true;
    }
}
