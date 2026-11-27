<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use ITHilbert\UserAuth\Rules\HaeufigesPasswortRule;

/**
 * Eingaberegeln für ein neues Passwort (Ändern und Zurücksetzen). Die Wiederverwendung prüft der
 * UseCase `PasswortSetzen` – beim Zurücksetzen erst nach gültigem Token.
 */
final class NeuesPasswortRegeln
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function regeln(): array
    {
        $password = ['required', 'string', 'min:'.self::minLaenge(), 'confirmed'];

        if (filter_var(config('userauth.passwort.haeufige_ablehnen', false), FILTER_VALIDATE_BOOLEAN)) {
            $password[] = new HaeufigesPasswortRule;
        }

        return [
            'password' => $password,
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
