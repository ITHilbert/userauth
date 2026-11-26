<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Listeners;

use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyBestaetigungen;

/**
 * Anmelden (auch Impersonate und dessen Ende) und Abmelden verwerfen die Bestätigung vor „Passkey hinzufügen“
 * (passkey-bestaetigung, AK1, AK9).
 */
final class PasskeyBestaetigungVerwerfen
{
    public function __construct(private readonly PasskeyBestaetigungen $bestaetigungen) {}

    public function handle(): void
    {
        $this->bestaetigungen->vergessen();
    }
}
