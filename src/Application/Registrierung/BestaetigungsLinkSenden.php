<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Registrierung;

use Carbon\CarbonImmutable;
use ITHilbert\UserAuth\Application\Contracts\MailLinks;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\BestaetigungsLinkVersand;
use ITHilbert\UserAuth\Domain\Registrierung\BestaetigungsHash;

/**
 * Baut den signierten, zeitlich begrenzten Bestätigungslink (an Konto-ID und E-Mail gebunden, aus `app.url`)
 * und gibt ihn dem Versand. Gemeinsamer Schritt von Registrierung und „Link erneut anfordern“.
 */
final class BestaetigungsLinkSenden
{
    public function __construct(
        private readonly RegistrierungsEinstellungen $einstellungen,
        private readonly MailLinks $links,
        private readonly BestaetigungsLinkVersand $versand,
    ) {}

    public function handle(RegistriertesKonto $konto): void
    {
        $minuten = $this->einstellungen->gueltigMinuten();

        $url = $this->links->signiertFuerRoute(
            'register.bestaetigen',
            ['id' => $konto->id, 'hash' => BestaetigungsHash::fuer($konto->email)],
            CarbonImmutable::now()->addMinutes($minuten),
        );

        $this->versand->senden($konto->email, $url, $minuten);
    }
}
