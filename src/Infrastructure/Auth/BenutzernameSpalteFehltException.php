<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Auth;

/**
 * Konfigurationsfehler: `userauth.anmeldung_per` verlangt die Spalte `benutzername`, die Migration fehlt.
 */
final class BenutzernameSpalteFehltException extends \RuntimeException {}
