<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use Illuminate\Cache\RateLimiter;
use ITHilbert\UserAuth\Application\Auth\Contracts\OffeneAnmeldungen;
use ITHilbert\UserAuth\Application\Auth\Contracts\Sitzungsanmeldung;
use ITHilbert\UserAuth\Domain\Auth\OffeneAnmeldung;

/**
 * „Code erneut senden“: höchstens einmal pro 60 Sekunden je offener Anmeldung, der neue Code ersetzt den alten (AK19).
 * Der Versand beim Anmelden zählt nicht zur Grenze. Gezählt wird atomar (RateLimiter), nicht in der Sitzung.
 */
final class ZweiFaktorCodeErneutSenden
{
    public function __construct(
        private readonly OffeneAnmeldungen $anmeldungen,
        private readonly Sitzungsanmeldung $sitzung,
        private readonly ZweiFaktorCodeSenden $codeSenden,
        private readonly RateLimiter $limiter,
    ) {}

    public function handle(): CodeErneutSendenErgebnis
    {
        $anmeldung = $this->anmeldungen->laden();
        $nutzer = $anmeldung === null ? null : $this->sitzung->nutzer($anmeldung->nutzerId);

        if ($anmeldung === null || $nutzer === null) {
            $this->anmeldungen->verwerfen();

            return CodeErneutSendenErgebnis::KeineOffeneAnmeldung;
        }

        // hit() zählt atomar; nur der erste Treffer im Zeitfenster geht durch
        if ($this->limiter->hit('userauth-2fa-erneut:'.$anmeldung->kennung, OffeneAnmeldung::ERNEUT_SENDEN_SEKUNDEN) > 1) {
            return CodeErneutSendenErgebnis::ZuFrueh;
        }

        $this->codeSenden->handle($anmeldung, $nutzer);

        return CodeErneutSendenErgebnis::Gesendet;
    }
}
