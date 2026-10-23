<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Auth\Contracts\OffeneAnmeldungen;
use ITHilbert\UserAuth\Domain\Auth\OffeneAnmeldung;

/**
 * Legt die offene Zwei-Faktor-Anmeldung als einfaches Array in der Sitzung ab (`userauth.2fa`):
 * Nutzer-ID, „Angemeldet bleiben“, Ziel, Hash und Ablauf des Codes – nie den Code selbst (AK18).
 */
final class SessionOffeneAnmeldungen implements OffeneAnmeldungen
{
    private const SCHLUESSEL = 'userauth.2fa';

    public function __construct(private readonly Request $request) {}

    public function beginnen(OffeneAnmeldung $anmeldung): void
    {
        $this->session()->regenerate(true);
        $this->speichern($anmeldung);
    }

    public function speichern(OffeneAnmeldung $anmeldung): void
    {
        $this->session()->put(self::SCHLUESSEL, [
            'kennung' => $anmeldung->kennung,
            'nutzer_id' => $anmeldung->nutzerId,
            'angemeldet_bleiben' => $anmeldung->angemeldetBleiben,
            'ziel' => $anmeldung->ziel,
            'code_hash' => $anmeldung->codeHash,
            'code_gueltig_bis' => $anmeldung->codeGueltigBis?->getTimestamp(),
        ]);
    }

    public function laden(): ?OffeneAnmeldung
    {
        $daten = $this->session()->get(self::SCHLUESSEL);

        if (! is_array($daten)
            || ! is_string($daten['kennung'] ?? null)
            || ! (is_int($daten['nutzer_id'] ?? null) || is_string($daten['nutzer_id'] ?? null))) {
            return null;
        }

        $gueltigBis = $daten['code_gueltig_bis'] ?? null;

        return new OffeneAnmeldung(
            kennung: $daten['kennung'],
            nutzerId: $daten['nutzer_id'],
            angemeldetBleiben: (bool) ($daten['angemeldet_bleiben'] ?? false),
            ziel: is_string($daten['ziel'] ?? null) ? $daten['ziel'] : null,
            codeHash: is_string($daten['code_hash'] ?? null) ? $daten['code_hash'] : null,
            codeGueltigBis: is_int($gueltigBis) ? CarbonImmutable::createFromTimestamp($gueltigBis) : null,
        );
    }

    public function verwerfen(): void
    {
        $this->session()->forget(self::SCHLUESSEL);
    }

    public function zielEntnehmen(): ?string
    {
        $ziel = $this->session()->pull('url.intended');

        return is_string($ziel) && $ziel !== '' ? $ziel : null;
    }

    private function session(): Session
    {
        return $this->request->session();
    }
}
