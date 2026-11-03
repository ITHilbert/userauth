<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Registrierung\BestaetigungsLinkErneutSenden;
use ITHilbert\UserAuth\Application\Registrierung\EmailBestaetigen;
use ITHilbert\UserAuth\Application\Registrierung\Exceptions\RegistrierungFalschKonfiguriertException;
use ITHilbert\UserAuth\Application\Registrierung\Registrieren;
use ITHilbert\UserAuth\Http\Requests\BestaetigungsLinkAnfordernRequest;
use ITHilbert\UserAuth\Http\Requests\RegistrierenRequest;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Selbstregistrierung per E-Mail und Passwort. Die Routen sind immer registriert; bei ausgeschalteter
 * Registrierung (`userauth.registration.enabled`) antwortet EnsureRegistrationEnabled mit 404.
 * Drosselung je IP an den Routen.
 */
final class RegistrierungController
{
    public function create(): View
    {
        return view('userauth::registrierung.formular');
    }

    /**
     * Gleiche Antwort für neue und schon vergebene Adressen.
     *
     * @throws RegistrierungFalschKonfiguriertException
     */
    public function store(RegistrierenRequest $request, Registrieren $registrieren): RedirectResponse
    {
        $registrieren->handle(
            (string) $request->validated('name'),
            (string) $request->validated('email'),
            (string) $request->validated('password'),
        );

        return redirect()->route('register.bestaetigung')
            ->with('status', __('userauth::registrierung.mail_gesendet'));
    }

    /**
     * Hinweis nach der Registrierung, Fehlerseite zu ungültigen Links und Formular „Link erneut anfordern“.
     */
    public function bestaetigung(): View
    {
        return view('userauth::registrierung.bestaetigung');
    }

    /**
     * Gleiche Antwort für jede Eingabe (bekannt, unbekannt, bestätigt, gelöscht, gedrosselt).
     */
    public function erneut(BestaetigungsLinkAnfordernRequest $request, BestaetigungsLinkErneutSenden $erneutSenden): RedirectResponse
    {
        $erneutSenden->handle((string) $request->validated('email'));

        return redirect()->route('register.bestaetigung')
            ->with('status', __('userauth::registrierung.link_erneut_gesendet'));
    }

    public function bestaetigen(Request $request, string $id, string $hash, EmailBestaetigen $bestaetigen): RedirectResponse
    {
        if (! $request->hasValidSignature() || ! $bestaetigen->handle($id, $hash)) {
            return redirect()->route('register.bestaetigung')
                ->with('fehler', __('userauth::registrierung.link_ungueltig'));
        }

        return redirect()->route('login')->with('status', __('userauth::registrierung.bestaetigt'));
    }

    /**
     * Andere Methode als GET auf den Bestätigungslink: 405 wie ohne eigene Route. Die Route gibt es nur, damit
     * bei ausgeschalteter Registrierung die Middleware 404 liefert (sonst 405 aus einer GET-Fallback-Route).
     */
    public function nurGet(): never
    {
        throw new MethodNotAllowedHttpException(['GET', 'HEAD']);
    }
}
