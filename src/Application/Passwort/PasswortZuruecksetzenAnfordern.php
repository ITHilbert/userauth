<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Timebox;
use ITHilbert\UserAuth\Application\Auth\AnmeldeEinstellungen;
use ITHilbert\UserAuth\Application\Contracts\MailLinks;
use ITHilbert\UserAuth\Application\Passwort\Contracts\PasswortKonten;
use ITHilbert\UserAuth\Application\Passwort\Contracts\ResetLinkVersand;
use ITHilbert\UserAuth\Application\Passwort\Contracts\ResetTokens;
use ITHilbert\UserAuth\Domain\Auth\AnmeldeModus;

/**
 * Fordert einen Reset-Link an; die Mail geht an die gespeicherte Adresse des Kontos. Höchstens ein Link
 * je Eingabe pro 60 Sekunden – eigene Drosselung, unabhängig von `auth.passwords.*.throttle` des Hosts (Entscheidung 0008).
 * Modus `email`: Token über den Laravel-Password-Broker, Schlüssel und Link mit der E-Mail (wie vor #1311).
 * Modus `benutzername`/`beides`: Konto über die Kennung, Token je Konto (gemeinsame E-Mails), Link mit der Kennung.
 * Ergebnis bewusst ohne Rückgabe: bekannte, unbekannte, mehrdeutige und gelöschte Konten sind nicht unterscheidbar.
 */
final class PasswortZuruecksetzenAnfordern
{
    private const SPERRE_SEKUNDEN = 60;

    /** Laufzeit wie die Timebox des Laravel-Brokers. */
    private const TIMEBOX_MIKROSEKUNDEN = 200_000;

    public function __construct(
        private readonly PasswortKonten $konten,
        private readonly PasswordBroker $broker,
        private readonly ResetTokens $tokens,
        private readonly ResetLinkVersand $versand,
        private readonly MailLinks $links,
        private readonly Repository $config,
        private readonly RateLimiter $limiter,
        private readonly AnmeldeEinstellungen $einstellungen,
    ) {}

    public function handle(string $kennung): void
    {
        // Gilt für jede Eingabe (auch ohne Konto); hit() zählt atomar, nur der erste Treffer im Zeitfenster geht durch
        if ($this->limiter->hit($this->drosselSchluessel($kennung), self::SPERRE_SEKUNDEN) > 1) {
            return;
        }

        $modus = $this->einstellungen->modus();

        if ($modus === AnmeldeModus::Email) {
            $this->perEmail($kennung);

            return;
        }

        // Gleiche Laufzeit (Timebox) für gefundene, unbekannte und mehrdeutige Kennungen
        (new Timebox)->call(function () use ($modus, $kennung): void {
            $this->perKennung($modus, $kennung);
        }, self::TIMEBOX_MIKROSEKUNDEN);
    }

    private function perEmail(string $email): void
    {
        // Auch ohne Treffer über den Broker: gleiche Laufzeit (Timebox) für bekannte und unbekannte Adressen
        $this->broker->sendResetLink(
            ['email' => $this->konten->gespeicherteEmail($email) ?? $email],
            function (CanResetPassword $nutzer, #[\SensitiveParameter] string $token): void {
                $adresse = $nutzer->getEmailForPasswordReset();

                $this->senden($adresse, $token, $adresse);
            },
        );
    }

    private function perKennung(AnmeldeModus $modus, string $kennung): void
    {
        $konto = $this->konten->zurKennung($modus, $kennung);

        if ($konto === null) {
            return;
        }

        $token = $this->tokens->ausstellen($konto->kontoId);

        if ($token === null) {
            return;
        }

        $this->senden($konto->email, $token, $konto->kennung);
    }

    private function senden(string $adresse, #[\SensitiveParameter] string $token, string $kennungImLink): void
    {
        $this->versand->senden(
            $adresse,
            $this->links->fuerRoute('password.editwithtoken', ['token' => $token, 'email' => $kennungImLink]),
            $this->gueltigMinuten(),
        );
    }

    private function drosselSchluessel(string $kennung): string
    {
        return 'userauth-passwort-link:'.hash('sha256', mb_strtolower(trim($kennung)));
    }

    private function gueltigMinuten(): int
    {
        $broker = (string) $this->config->get('auth.defaults.passwords', 'users');

        return (int) $this->config->get("auth.passwords.{$broker}.expire", 60);
    }
}
