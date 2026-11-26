<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyAbgelehntException;
use ITHilbert\UserAuth\Application\Passkey\PasskeyBestaetigen;
use ITHilbert\UserAuth\Application\Passkey\PasskeyBestaetigung;
use ITHilbert\UserAuth\Application\Passkey\PasskeyBestaetigungVorbereiten;
use ITHilbert\UserAuth\Http\Requests\PasskeyBestaetigenRequest;

/**
 * Bestätigung vor „Passkey hinzufügen“ (passkey-bestaetigung, AK2, AK4 bis AK6): per aktuellem Passwort oder per
 * vorhandenem Passkey. Erfolg antwortet mit 204 ohne Inhalt, jede Ablehnung mit 422. Der Benutzer kommt immer aus
 * der Sitzung.
 */
final class PasskeyBestaetigungController extends Controller
{
    public function passwort(PasskeyBestaetigenRequest $request, PasskeyBestaetigung $bestaetigung): Response
    {
        $bestaetigung->erteilen($request->user()->getAuthIdentifier());

        return response()->noContent();
    }

    public function optionen(Request $request, PasskeyBestaetigungVorbereiten $vorbereiten): JsonResponse
    {
        try {
            return response()->json($vorbereiten->handle($request->user()->getAuthIdentifier()));
        } catch (PasskeyAbgelehntException) {
            return $this->abgelehnt();
        }
    }

    public function assertion(Request $request, PasskeyBestaetigen $bestaetigen): Response|JsonResponse
    {
        try {
            $bestaetigen->handle($request->user()->getAuthIdentifier(), json_decode($request->getContent(), true));
        } catch (PasskeyAbgelehntException) {
            return $this->abgelehnt();
        }

        return response()->noContent();
    }

    private function abgelehnt(): JsonResponse
    {
        return response()->json(['message' => __('userauth::passkey.fehler.bestaetigung')], 422);
    }
}
