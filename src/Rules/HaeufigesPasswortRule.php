<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Lehnt Passwörter ab, die in der mitgelieferten Liste häufiger Passwörter stehen (Vergleich ohne
 * Randleerzeichen und ohne Groß-/Kleinschreibung). Keine Netzabfrage; die Fehlermeldung nennt das Passwort nie.
 * Fehlt die Liste oder ist sie unlesbar, lehnt die Regel nichts ab.
 */
final class HaeufigesPasswortRule implements ValidationRule
{
    /** @var array<string, int>|null */
    private static ?array $liste = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $kandidat = mb_strtolower(trim($value));

        if ($kandidat !== '' && isset(self::liste()[$kandidat])) {
            $fail('userauth::password.haeufig')->translate();
        }
    }

    /**
     * @return array<string, int>
     */
    private static function liste(): array
    {
        if (self::$liste !== null) {
            return self::$liste;
        }

        $pfad = __DIR__.'/../Resources/daten/haeufige-passwoerter.txt';
        $zeilen = is_readable($pfad) ? @file($pfad, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;

        return self::$liste = is_array($zeilen) ? array_flip($zeilen) : [];
    }
}
