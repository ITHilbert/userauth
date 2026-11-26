<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ITHilbert\UserAuth\Application\Passkey\EigenePasskeys;
use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyAbgelehntException;
use ITHilbert\UserAuth\Application\Passkey\PasskeyLoeschen;
use ITHilbert\UserAuth\Application\Passkey\PasskeyRegistrieren;
use ITHilbert\UserAuth\Application\Passkey\PasskeyRegistrierungVorbereiten;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Eigene Passkeys des angemeldeten Benutzers: Liste, Hinzufügen (JSON für das Skript), Löschen (AK8 bis AK11).
 * Der Benutzer kommt immer aus der Sitzung, nie aus der Anfrage.
 */
final class PasskeyController extends Controller
{
    public function index(Request $request, EigenePasskeys $passkeys): View
    {
        return view('userauth::passkey.index', ['uebersicht' => $passkeys->handle($request->user()->getAuthIdentifier())]);
    }

    public function optionen(Request $request, PasskeyRegistrierungVorbereiten $vorbereiten): JsonResponse
    {
        try {
            return response()->json($vorbereiten->handle($request->user()->getAuthIdentifier()));
        } catch (PasskeyAbgelehntException) {
            return $this->abgelehnt();
        }
    }

    public function registrieren(Request $request, PasskeyRegistrieren $registrieren): JsonResponse
    {
        $daten = json_decode($request->getContent(), true);
        $daten = is_array($daten) ? $daten : [];

        try {
            $registrieren->handle($request->user()->getAuthIdentifier(), $daten['credential'] ?? null, $daten['name'] ?? null);
        } catch (PasskeyAbgelehntException) {
            return $this->abgelehnt();
        }

        $meldung = __('userauth::passkey.status.hinzugefuegt');
        $request->session()->flash('status', $meldung);

        return response()->json(['message' => $meldung, 'redirect' => route('userauth.passkey.index')], 201);
    }

    public function destroy(Request $request, int $id, PasskeyLoeschen $loeschen): RedirectResponse
    {
        abort_unless($loeschen->handle($request->user()->getAuthIdentifier(), $id), 404);

        return redirect()->route('userauth.passkey.index')->with('status', __('userauth::passkey.status.geloescht'));
    }

    /**
     * GET auf die Registrierungsrouten: 405 wie ohne eigene Route. Die Route gibt es nur, damit bei ausgeschaltetem
     * Passkey die Middleware für jede Methode 404 liefert (wie `RegistrierungController::nurGet`).
     */
    public function nurPost(): never
    {
        throw new MethodNotAllowedHttpException(['POST']);
    }

    private function abgelehnt(): JsonResponse
    {
        return response()->json(['message' => __('userauth::passkey.fehler.registrierung')], 422);
    }
}
