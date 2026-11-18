<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

/**
 * Eingaberegeln für ein neues Passwort (Ändern und Zurücksetzen). Die Wiederverwendung prüft der
 * UseCase `PasswortSetzen` – beim Zurücksetzen erst nach gültigem Token.
 */
final class NeuesPasswortRegeln
{
    /**
     * @return array<string, list<string>>
     */
    public static function regeln(): array
    {
        return [
            'password' => ['required', 'string', 'min:'.self::minLaenge(), 'confirmed'],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    private static function minLaenge(): int
    {
        $wert = config('userauth.passwort.min_laenge', 8);

        if (is_string($wert) && preg_match('/^\s*\d+\s*$/', $wert) === 1) {
            $wert = (int) $wert;
        }

        return is_int($wert) && $wert >= 1 ? $wert : 8;
    }
}
