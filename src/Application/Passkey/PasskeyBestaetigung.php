<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

use Carbon\CarbonImmutable;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyBestaetigungen;

/**
 * Anwendungsfall: Bestätigung vor „Passkey hinzufügen“ erteilen bzw. prüfen (passkey-bestaetigung, AK2, AK3,
 * Randfall 2, Randfall 3). Sie gilt bis jetzt + `bestaetigung_minuten` einschließlich, nur für den Benutzer, für den
 * sie erteilt wurde, und wird beim Hinzufügen nicht verbraucht. Erteilt wird sie erst, nachdem der Aufrufer den
 * Nachweis (aktuelles Passwort oder Passkey-Assertion) geprüft hat.
 */
final class PasskeyBestaetigung
{
    public function __construct(
        private readonly PasskeyBestaetigungen $bestaetigungen,
        private readonly PasskeyEinstellungen $einstellungen,
    ) {}

    public function erteilen(int|string $userId): void
    {
        $bis = CarbonImmutable::now()->addMinutes($this->einstellungen->bestaetigungMinuten())->getTimestamp();

        $this->bestaetigungen->merken($userId, $bis);
    }

    public function gueltig(int|string $userId): bool
    {
        $bis = $this->bestaetigungen->gueltigBis($userId);

        return $bis !== null && CarbonImmutable::now()->getTimestamp() <= $bis;
    }
}
