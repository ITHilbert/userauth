<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Registrierung;

use Illuminate\Cache\RateLimiter;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\RegistrierungsKonten;

/**
 * „Link erneut anfordern“: Eine Mail geht nur an ein nicht gelöschtes Konto mit „Bestätigung offen“.
 * Höchstens ein Link je E-Mail pro 60 Sekunden – eigene Drosselung wie beim Passwort-Reset (Entscheidung 0008).
 * Ohne Rückgabe: Für den Aufrufer sind alle Fälle gleich.
 */
final class BestaetigungsLinkErneutSenden
{
    private const SPERRE_SEKUNDEN = 60;

    public function __construct(
        private readonly RegistrierungsKonten $konten,
        private readonly BestaetigungsLinkSenden $linkSenden,
        private readonly RateLimiter $limiter,
    ) {}

    public function handle(string $email): void
    {
        // Gilt für jede Eingabe; hit() zählt atomar, nur der erste Treffer im Zeitfenster geht durch
        if ($this->limiter->hit($this->drosselSchluessel($email), self::SPERRE_SEKUNDEN) > 1) {
            return;
        }

        $konto = $this->konten->offenesKontoZurEmail($email);

        if ($konto !== null) {
            $this->linkSenden->handle($konto);
        }
    }

    private function drosselSchluessel(string $email): string
    {
        return 'userauth-registrierung-link:'.hash('sha256', mb_strtolower(trim($email)));
    }
}
