<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use ITHilbert\LaravelKit\Helpers\Breadcrumb;
use ITHilbert\UserAuth\Application\Passwort\PasswortSetzen;
use ITHilbert\UserAuth\Application\Passwort\PasswortZuruecksetzen;
use ITHilbert\UserAuth\Application\Passwort\PasswortZuruecksetzenAnfordern;
use ITHilbert\UserAuth\Application\Passwort\PasswortZuruecksetzenErgebnis;
use ITHilbert\UserAuth\Domain\Passwort\Exceptions\PasswortBereitsVerwendetException;
use ITHilbert\UserAuth\Http\Requests\PasswortAendernRequest;
use ITHilbert\UserAuth\Http\Requests\PasswortZuruecksetzenAnfordernRequest;
use ITHilbert\UserAuth\Http\Requests\PasswortZuruecksetzenRequest;

/**
 * Passwort ändern (angemeldet) und Passwort vergessen. Drosselung je IP an den Routen.
 */
final class PasswordController
{
    /**
     * Formular zum Passwort ändern öffnen.
     */
    public function edit(): View
    {
        $breadcrumb = new Breadcrumb;
        $breadcrumb->add(trans('userauth::password.header_change'));

        return view('userauth::password.edit')->with(['breadcrumb' => $breadcrumb]);
    }

    /**
     * Passwort-Änderung speichern.
     */
    public function update(PasswortAendernRequest $request, PasswortSetzen $passwortSetzen): RedirectResponse
    {
        $this->neuesPasswortSetzen(fn () => $passwortSetzen->handle($request->user(), (string) $request->validated('password')));

        return $this->nachPasswortAenderung();
    }

    /**
     * Formular, um den Reset-Link anzufordern.
     */
    public function forgotten(): View
    {
        return view('userauth::password.forgotten');
    }

    /**
     * Reset-Link anfordern; gleiche Antwort für bekannte und unbekannte Adressen.
     */
    public function sendtocken(PasswortZuruecksetzenAnfordernRequest $request, PasswortZuruecksetzenAnfordern $anfordern): RedirectResponse
    {
        $anfordern->handle((string) $request->validated('email'));

        return redirect()->route('password.tokensend')
            ->with('message', trans('userauth::password.link_angefordert'));
    }

    /**
     * Hinweis „Link angefordert“.
     */
    public function tokensend(): View
    {
        return view('userauth::password.tokensend');
    }

    /**
     * Formular „Neues Passwort“ aus dem Reset-Link.
     */
    public function editwithtoken(string $token, string $email): View
    {
        return view('userauth::password.editwithtoken')->with(['token' => $token, 'email' => $email]);
    }

    /**
     * Passwort mit Reset-Token setzen.
     *
     * @throws ValidationException
     */
    public function updatewithtoken(PasswortZuruecksetzenRequest $request, PasswortZuruecksetzen $zuruecksetzen): RedirectResponse
    {
        $ergebnis = $this->neuesPasswortSetzen(fn (): PasswortZuruecksetzenErgebnis => $zuruecksetzen->handle(
            (string) $request->validated('email'),
            (string) $request->validated('pwtoken'),
            (string) $request->validated('password'),
        ));

        if ($ergebnis !== PasswortZuruecksetzenErgebnis::Zurueckgesetzt) {
            throw ValidationException::withMessages(['pwtoken' => trans('userauth::password.link_ungueltig')])
                ->redirectTo(route('password.forgotten'));
        }

        return $this->nachPasswortAenderung();
    }

    /**
     * Bereits verwendetes Passwort → Validierungsfehler am Feld `password`.
     *
     * @template T
     *
     * @param  Closure(): T  $setzen
     * @return T
     *
     * @throws ValidationException
     */
    private function neuesPasswortSetzen(Closure $setzen): mixed
    {
        try {
            return $setzen();
        } catch (PasswortBereitsVerwendetException) {
            throw ValidationException::withMessages(['password' => trans('userauth::password.bereits_verwendet')]);
        }
    }

    private function nachPasswortAenderung(): RedirectResponse
    {
        return redirect(config('userauth.redirect_after_login', '/'))
            ->with('message', trans('userauth::password.geaendert'));
    }
}
