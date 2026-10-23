<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use ITHilbert\UserAuth\Application\Auth\Contracts\OffeneAnmeldungen;
use ITHilbert\UserAuth\Application\Auth\Contracts\ZweiFaktorCodeHasher;
use ITHilbert\UserAuth\Application\Auth\Contracts\ZweiFaktorCodeVersand;
use ITHilbert\UserAuth\Domain\Auth\OffeneAnmeldung;
use ITHilbert\UserAuth\Domain\Auth\ZweiFaktorCode;

/**
 * Einzige Stelle, die einen Zwei-Faktor-Code erzeugt und verschickt – beim Anmelden wie beim erneuten Senden.
 * Der neue Code ersetzt den bisherigen; abgelegt wird nur sein Hash (AK18, AK19).
 */
final class ZweiFaktorCodeSenden
{
    public function __construct(
        private readonly OffeneAnmeldungen $anmeldungen,
        private readonly ZweiFaktorCodeHasher $hasher,
        private readonly ZweiFaktorCodeVersand $versand,
    ) {}

    public function handle(OffeneAnmeldung $anmeldung, Authenticatable $nutzer): void
    {
        $code = ZweiFaktorCode::erzeugen();

        $this->anmeldungen->speichern(
            $anmeldung->mitCode($this->hasher->hash($code->wert, $anmeldung->kennung), CarbonImmutable::now()),
        );

        $this->versand->senden($nutzer, $code->wert, OffeneAnmeldung::CODE_GUELTIG_MINUTEN);
    }
}
