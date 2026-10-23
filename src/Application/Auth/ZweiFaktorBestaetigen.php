<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Events\Dispatcher;
use ITHilbert\UserAuth\Application\Auth\Contracts\OffeneAnmeldungen;
use ITHilbert\UserAuth\Application\Auth\Contracts\Sitzungsanmeldung;
use ITHilbert\UserAuth\Application\Auth\Contracts\ZweiFaktorCodeHasher;
use ITHilbert\UserAuth\Application\Auth\Events\ZweiFaktorCodeAbgelehnt;
use ITHilbert\UserAuth\Domain\Auth\OffeneAnmeldung;

/**
 * Prüft den Zwei-Faktor-Code der offenen Anmeldung dieser Sitzung (AK15–AK17, AK22).
 * Fehlversuche zählt der RateLimiter atomar je offener Anmeldung – parallele Anfragen derselben Sitzung
 * können den Zähler so nicht überschreiben.
 */
final class ZweiFaktorBestaetigen
{
    /** Lebensdauer des Fehlversuchszählers; länger als jede Sitzung mit offener Anmeldung. */
    private const ZAEHLER_SEKUNDEN = 86400;

    public function __construct(
        private readonly OffeneAnmeldungen $anmeldungen,
        private readonly Sitzungsanmeldung $sitzung,
        private readonly ZweiFaktorCodeHasher $hasher,
        private readonly RateLimiter $limiter,
        private readonly Dispatcher $events,
    ) {}

    public function handle(#[\SensitiveParameter] string $code): ZweiFaktorErgebnis
    {
        $anmeldung = $this->anmeldungen->laden();
        $nutzer = $anmeldung === null ? null : $this->sitzung->nutzer($anmeldung->nutzerId);

        if ($anmeldung === null || $nutzer === null) {
            $this->anmeldungen->verwerfen();

            return ZweiFaktorErgebnis::mit(ZweiFaktorStatus::KeineOffeneAnmeldung);
        }

        $zaehler = $this->zaehlerSchluessel($anmeldung);
        $versuch = $this->limiter->hit($zaehler, self::ZAEHLER_SEKUNDEN);

        if ($versuch > OffeneAnmeldung::MAX_FEHLVERSUCHE) {
            $this->anmeldungen->verwerfen();

            return ZweiFaktorErgebnis::mit(ZweiFaktorStatus::Gesperrt);
        }

        $abgelaufen = $anmeldung->codeIstAbgelaufen(CarbonImmutable::now());

        if (! $abgelaufen && $anmeldung->codeHash !== null && $this->hasher->stimmt($code, $anmeldung->kennung, $anmeldung->codeHash)) {
            // Erst verwerfen, dann anmelden: die neue Sitzung trägt keinen Code mehr, die alte wird beim Anmelden zerstört
            $this->anmeldungen->verwerfen();
            $this->limiter->clear($zaehler);
            $this->sitzung->anmelden($nutzer, $anmeldung->angemeldetBleiben);

            return ZweiFaktorErgebnis::bestanden($anmeldung->ziel);
        }

        $this->events->dispatch(new ZweiFaktorCodeAbgelehnt($nutzer));

        if ($versuch >= OffeneAnmeldung::MAX_FEHLVERSUCHE) {
            $this->anmeldungen->verwerfen();

            return ZweiFaktorErgebnis::mit(ZweiFaktorStatus::Gesperrt);
        }

        return ZweiFaktorErgebnis::mit($abgelaufen ? ZweiFaktorStatus::Abgelaufen : ZweiFaktorStatus::Abgelehnt);
    }

    private function zaehlerSchluessel(OffeneAnmeldung $anmeldung): string
    {
        return 'userauth-2fa-fehlversuche:'.$anmeldung->kennung;
    }
}
