<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Benutzer;

use RuntimeException;

/**
 * Ein Model-Ereignis des Hosts (`restoring`, `forceDeleting`) hat das Wiederherstellen bzw. endgültige Löschen
 * abgebrochen. Die Transaktion der Neuanlage wird zurückgerollt.
 */
final class GeloeschterBenutzerUnveraendertException extends RuntimeException {}
