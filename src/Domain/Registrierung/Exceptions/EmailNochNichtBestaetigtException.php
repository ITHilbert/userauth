<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Registrierung\Exceptions;

use DomainException;

/**
 * Zugangsdaten stimmen, aber das Konto aus der Registrierung hat seine E-Mail noch nicht bestätigt.
 */
final class EmailNochNichtBestaetigtException extends DomainException {}
