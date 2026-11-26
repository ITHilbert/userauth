<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Oidc\Contracts;

use ITHilbert\UserAuth\Application\Oidc\OidcVorgang;

/**
 * Port: hält `state` und `nonce` eines begonnenen OIDC-Vorgangs zwischen Redirect und Callback (Sitzung).
 */
interface OidcVorgaenge
{
    public function merken(OidcVorgang $vorgang): void;

    /**
     * Liefert den Vorgang und entfernt ihn zugleich (einmal verwendbar); null, wenn keiner begonnen wurde.
     */
    public function entnehmen(): ?OidcVorgang;
}
