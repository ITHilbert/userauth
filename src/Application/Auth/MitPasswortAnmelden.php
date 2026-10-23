<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use ITHilbert\UserAuth\Application\Auth\Contracts\Anmeldedaten;

/**
 * Anmeldung mit E-Mail und Passwort. Die Zugangsdaten werden nur geprüft; angemeldet wird in
 * CompleteAuthentication – bei aktiver 2FA erst nach dem Code (AK14).
 */
final class MitPasswortAnmelden
{
    public function __construct(
        private readonly Anmeldedaten $anmeldedaten,
        private readonly CompleteAuthentication $completeAuthentication,
    ) {}

    /**
     * @param  array<string, mixed>  $zugangsdaten
     * @return AuthenticationNextStep|null null bei falschen Zugangsdaten
     */
    public function handle(#[\SensitiveParameter] array $zugangsdaten, bool $angemeldetBleiben): ?AuthenticationNextStep
    {
        $nutzer = $this->anmeldedaten->pruefen($zugangsdaten, $angemeldetBleiben);

        if ($nutzer === null) {
            return null;
        }

        return $this->completeAuthentication->handle($nutzer, $angemeldetBleiben);
    }
}
