<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\PasswordBroker;
use ITHilbert\UserAuth\Application\Passwort\Contracts\PasswortKonten;
use ITHilbert\UserAuth\Domain\Passwort\Exceptions\PasswortBereitsVerwendetException;

/**
 * Setzt das Passwort mit einem Reset-Token zurück. Prüfung und Verbrauch des Tokens übernimmt
 * der Laravel-Password-Broker (gehashte Tokens, Ablauf `expire`); gesetzt wird über `PasswortSetzen`.
 */
final class PasswortZuruecksetzen
{
    public function __construct(
        private readonly PasswortKonten $konten,
        private readonly PasswordBroker $broker,
        private readonly PasswortSetzen $passwortSetzen,
    ) {}

    /**
     * @throws PasswortBereitsVerwendetException das Token bleibt dann gültig
     */
    public function handle(string $email, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $neuesPasswort): PasswortZuruecksetzenErgebnis
    {
        $status = $this->broker->reset(
            [
                'email' => $this->konten->gespeicherteEmail($email) ?? $email,
                'token' => $token,
                'password' => $neuesPasswort,
            ],
            function (Authenticatable $nutzer, #[\SensitiveParameter] string $passwort): void {
                $this->passwortSetzen->handle($nutzer, $passwort);
            },
        );

        return $status === PasswordBroker::PASSWORD_RESET
            ? PasswortZuruecksetzenErgebnis::Zurueckgesetzt
            : PasswortZuruecksetzenErgebnis::LinkUngueltig;
    }
}
