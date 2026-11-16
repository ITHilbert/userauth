<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Timebox;
use ITHilbert\UserAuth\Application\Auth\AnmeldeEinstellungen;
use ITHilbert\UserAuth\Application\Passwort\Contracts\PasswortKonten;
use ITHilbert\UserAuth\Application\Passwort\Contracts\ResetTokens;
use ITHilbert\UserAuth\Domain\Auth\AnmeldeModus;
use ITHilbert\UserAuth\Domain\Passwort\Exceptions\PasswortBereitsVerwendetException;

/**
 * Setzt das Passwort mit einem Reset-Token zurück; gesetzt wird über `PasswortSetzen`.
 * Modus `email`: Prüfung und Verbrauch des Tokens im Laravel-Password-Broker (gehashte Tokens, Ablauf `expire`).
 * Modus `benutzername`/`beides`: Konto über die Kennung aus dem Link, Token je Konto (`ResetTokens`) –
 * ein Link gilt nur für das Konto, für das er ausgestellt wurde.
 */
final class PasswortZuruecksetzen
{
    /** Laufzeit wie die Timebox des Laravel-Brokers. */
    private const TIMEBOX_MIKROSEKUNDEN = 200_000;

    public function __construct(
        private readonly PasswortKonten $konten,
        private readonly PasswordBroker $broker,
        private readonly ResetTokens $tokens,
        private readonly PasswortSetzen $passwortSetzen,
        private readonly AnmeldeEinstellungen $einstellungen,
    ) {}

    /**
     * @param  string  $kennung  E-Mail (Modus `email`) bzw. Kennung des Kontos aus dem Link
     *
     * @throws PasswortBereitsVerwendetException das Token bleibt dann gültig
     */
    public function handle(string $kennung, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $neuesPasswort): PasswortZuruecksetzenErgebnis
    {
        $modus = $this->einstellungen->modus();

        if ($modus === AnmeldeModus::Email) {
            return $this->perEmail($kennung, $token, $neuesPasswort);
        }

        return (new Timebox)->call(
            fn (Timebox $timebox): PasswortZuruecksetzenErgebnis => $this->perKennung($timebox, $modus, $kennung, $token, $neuesPasswort),
            self::TIMEBOX_MIKROSEKUNDEN,
        );
    }

    private function perEmail(string $email, #[\SensitiveParameter] string $token, #[\SensitiveParameter] string $neuesPasswort): PasswortZuruecksetzenErgebnis
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

    /**
     * @throws PasswortBereitsVerwendetException
     */
    private function perKennung(
        Timebox $timebox,
        AnmeldeModus $modus,
        string $kennung,
        #[\SensitiveParameter] string $token,
        #[\SensitiveParameter] string $neuesPasswort,
    ): PasswortZuruecksetzenErgebnis {
        $konto = $this->konten->zurKennung($modus, $kennung);

        if ($konto === null || ! $this->tokens->gueltig($konto->kontoId, $token)) {
            return PasswortZuruecksetzenErgebnis::LinkUngueltig;
        }

        $this->passwortSetzen->handle($konto->nutzer, $neuesPasswort);
        $this->tokens->verbrauchen($konto->kontoId);
        $timebox->returnEarly();

        return PasswortZuruecksetzenErgebnis::Zurueckgesetzt;
    }
}
