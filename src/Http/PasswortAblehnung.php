<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http;

use Closure;
use Illuminate\Validation\ValidationException;
use ITHilbert\UserAuth\Domain\Passwort\Exceptions\PasswortBereitsVerwendetException;

/**
 * Übersetzt die Ablehnung eines bereits verwendeten Passworts in einen Validierungsfehler am Feld `password`.
 * Gemeinsam genutzt von „Passwort ändern/zurücksetzen“ und der Benutzerverwaltung.
 */
final class PasswortAblehnung
{
    /**
     * @template T
     *
     * @param  Closure(): T  $setzen
     * @return T
     *
     * @throws ValidationException
     */
    public static function alsValidierungsfehler(Closure $setzen): mixed
    {
        try {
            return $setzen();
        } catch (PasswortBereitsVerwendetException) {
            throw ValidationException::withMessages(['password' => trans('userauth::password.bereits_verwendet')]);
        }
    }
}
