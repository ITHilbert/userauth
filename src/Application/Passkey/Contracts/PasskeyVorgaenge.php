<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey\Contracts;

use ITHilbert\UserAuth\Application\Passkey\PasskeyVorgang;
use ITHilbert\UserAuth\Application\Passkey\PasskeyVorgangsArt;

/**
 * Port: hält je Art genau einen begonnenen Passkey-Vorgang zwischen Optionen und Antwort (Sitzung).
 */
interface PasskeyVorgaenge
{
    /**
     * Merkt den Vorgang; ein älterer Vorgang derselben Art wird ersetzt.
     */
    public function merken(PasskeyVorgangsArt $art, PasskeyVorgang $vorgang): void;

    /**
     * Liefert den Vorgang und entfernt ihn zugleich (einmal verwendbar); null, wenn keiner begonnen wurde.
     */
    public function entnehmen(PasskeyVorgangsArt $art): ?PasskeyVorgang;
}
