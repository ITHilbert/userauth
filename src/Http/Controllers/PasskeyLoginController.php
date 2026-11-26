<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyAbgelehntException;
use ITHilbert\UserAuth\Application\Passkey\PasskeyAnmelden;
use ITHilbert\UserAuth\Application\Passkey\PasskeyAnmeldungVorbereiten;

/**
 * Anmeldung per Passkey für Gäste (JSON für das Skript `userauth.assets.passkey`). Jede Ablehnung antwortet mit
 * 422 und derselben allgemeinen Meldung (AK6).
 */
final class PasskeyLoginController extends Controller
{
    public function optionen(PasskeyAnmeldungVorbereiten $vorbereiten): JsonResponse
    {
        return response()->json($vorbereiten->handle());
    }

    public function anmelden(Request $request, PasskeyAnmelden $anmelden): JsonResponse
    {
        try {
            $anmelden->handle($this->json($request));
        } catch (PasskeyAbgelehntException) {
            return response()->json(['message' => __('userauth::passkey.fehler.anmeldung')], 422);
        }

        // Zieladresse wie redirect()->intended(): das gemerkte Ziel aus der Sitzung, sonst redirect_after_login
        return response()->json(['redirect' => $request->session()->pull('url.intended', config('userauth.redirect_after_login', '/home'))]);
    }

    /**
     * Body als JSON-Objekt (Array) oder null, wenn er kein JSON ist.
     */
    private function json(Request $request): mixed
    {
        return json_decode($request->getContent(), true);
    }
}
