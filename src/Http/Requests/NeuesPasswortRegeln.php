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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }
}
