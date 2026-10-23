<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use ITHilbert\UserAuth\Application\Auth\CodeErneutSendenErgebnis;
use ITHilbert\UserAuth\Application\Auth\Contracts\OffeneAnmeldungen;
use ITHilbert\UserAuth\Application\Auth\ZweiFaktorBestaetigen;
use ITHilbert\UserAuth\Application\Auth\ZweiFaktorCodeErneutSenden;
use ITHilbert\UserAuth\Application\Auth\ZweiFaktorStatus;
use ITHilbert\UserAuth\Domain\Auth\OffeneAnmeldung;
use ITHilbert\UserAuth\Http\Requests\ZweiFaktorCodeRequest;

/**
 * Code-Eingabe der Zwei-Faktor-Anmeldung. Nur für Gäste mit offener Anmeldung in der eigenen Sitzung.
 */
final class TwoFactorController
{
    public function index(OffeneAnmeldungen $anmeldungen): View|RedirectResponse
    {
        if ($anmeldungen->laden() === null) {
            return redirect()->route('login');
        }

        return view('userauth::auth.twoFactor', ['gueltigMinuten' => OffeneAnmeldung::CODE_GUELTIG_MINUTEN]);
    }

    public function store(ZweiFaktorCodeRequest $request, ZweiFaktorBestaetigen $bestaetigen): RedirectResponse
    {
        $ergebnis = $bestaetigen->handle($request->code());

        return match ($ergebnis->status) {
            ZweiFaktorStatus::Bestanden => redirect()->to($ergebnis->ziel ?? (string) config('userauth.redirect_after_login', '/')),
            ZweiFaktorStatus::Abgelehnt => $this->zurCodeEingabe(__('userauth::zwei_faktor.ungueltig')),
            ZweiFaktorStatus::Abgelaufen => $this->zurCodeEingabe(__('userauth::zwei_faktor.abgelaufen')),
            ZweiFaktorStatus::Gesperrt => redirect()->route('login')->withErrors(['two_factor_code' => __('userauth::zwei_faktor.gesperrt')]),
            ZweiFaktorStatus::KeineOffeneAnmeldung => redirect()->route('login'),
        };
    }

    public function resend(ZweiFaktorCodeErneutSenden $erneutSenden): RedirectResponse
    {
        return match ($erneutSenden->handle()) {
            CodeErneutSendenErgebnis::Gesendet => redirect()->route('verify.index')->with('status', __('userauth::zwei_faktor.erneut_gesendet')),
            CodeErneutSendenErgebnis::ZuFrueh => $this->zurCodeEingabe(__('userauth::zwei_faktor.zu_frueh', ['seconds' => OffeneAnmeldung::ERNEUT_SENDEN_SEKUNDEN])),
            CodeErneutSendenErgebnis::KeineOffeneAnmeldung => redirect()->route('login'),
            CodeErneutSendenErgebnis::NichtVersendet => redirect()->route('login')->withErrors(['two_factor_code' => __('userauth::zwei_faktor.versand_fehlgeschlagen')]),
        };
    }

    private function zurCodeEingabe(string $fehler): RedirectResponse
    {
        return redirect()->route('verify.index')->withErrors(['two_factor_code' => $fehler]);
    }
}
