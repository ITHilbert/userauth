<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use ITHilbert\UserAuth\Application\Passkey\PasskeysZuruecksetzen;

/**
 * Benutzerverwaltung: alle Passkeys eines Benutzers zurücksetzen (Recht `user_edit`, AK15).
 */
final class BenutzerPasskeysController extends Controller
{
    public function zuruecksetzen(int $id, PasskeysZuruecksetzen $zuruecksetzen): RedirectResponse
    {
        $anzahl = $zuruecksetzen->fuerBenutzer($id);
        abort_if($anzahl === null, 404);

        return redirect()->route('user.edit', $id)->with([
            'message' => __('userauth::passkey.status.zurueckgesetzt', ['anzahl' => $anzahl]),
            'alert-type' => 'success',
        ]);
    }
}
