<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use ITHilbert\UserAuth\Application\Passwort\Contracts\PasswortKonten;
use ITHilbert\UserAuth\Application\Passwort\Contracts\ResetLinkVersand;

/**
 * Fordert einen Reset-Link an. Token verwaltet der Laravel-Password-Broker; die Mail geht an die
 * gespeicherte Adresse des Kontos. Höchstens ein Link je E-Mail pro 60 Sekunden – eigene Drosselung,
 * unabhängig von `auth.passwords.*.throttle` des Hosts (Entscheidung 0008).
 * Ergebnis bewusst ohne Rückgabe: bekannte, unbekannte und gelöschte Adressen sind nicht unterscheidbar.
 */
final class PasswortZuruecksetzenAnfordern
{
    private const SPERRE_SEKUNDEN = 60;

    public function __construct(
        private readonly PasswortKonten $konten,
        private readonly PasswordBroker $broker,
        private readonly ResetLinkVersand $versand,
        private readonly UrlGenerator $urls,
        private readonly Repository $config,
        private readonly RateLimiter $limiter,
    ) {}

    public function handle(string $email): void
    {
        // Gilt für jede Eingabe (auch ohne Konto); hit() zählt atomar, nur der erste Treffer im Zeitfenster geht durch
        if ($this->limiter->hit($this->drosselSchluessel($email), self::SPERRE_SEKUNDEN) > 1) {
            return;
        }

        // Auch ohne Treffer über den Broker: gleiche Laufzeit (Timebox) für bekannte und unbekannte Adressen
        $this->broker->sendResetLink(
            ['email' => $this->konten->gespeicherteEmail($email) ?? $email],
            function (CanResetPassword $nutzer, #[\SensitiveParameter] string $token): void {
                $adresse = $nutzer->getEmailForPasswordReset();

                $this->versand->senden(
                    $adresse,
                    $this->urls->route('password.editwithtoken', ['token' => $token, 'email' => $adresse]),
                    $this->gueltigMinuten(),
                );
            },
        );
    }

    private function drosselSchluessel(string $email): string
    {
        return 'userauth-passwort-link:'.hash('sha256', mb_strtolower(trim($email)));
    }

    private function gueltigMinuten(): int
    {
        $broker = (string) $this->config->get('auth.defaults.passwords', 'users');

        return (int) $this->config->get("auth.passwords.{$broker}.expire", 60);
    }
}
