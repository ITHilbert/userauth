<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

/**
 * Art eines begonnenen Passkey-Vorgangs; je Art liegt höchstens ein Vorgang in der Sitzung.
 */
enum PasskeyVorgangsArt: string
{
    case Anmeldung = 'anmeldung';
    case Registrierung = 'registrierung';
}
