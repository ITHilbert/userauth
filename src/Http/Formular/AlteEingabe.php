<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Formular;

use Stringable;

/**
 * Liest die alte Eingabe (`old()`) für die Verwaltungsformulare. Einzelfelder liefern nur Text:
 * Kommt statt eines Einzelwerts eine Liste zurück (manipulierter POST), gilt die Vorgabe.
 */
final class AlteEingabe
{
    public static function text(string $feld, mixed $vorgabe = null): ?string
    {
        $wert = request()->old($feld);

        if ($wert === null || ! self::istText($wert)) {
            $wert = $vorgabe;
        }

        return self::istText($wert) ? (string) $wert : null;
    }

    /**
     * Kästchen einer Liste `feld[…]`: Nach einem Validierungsfehler gilt die gesendete Auswahl, sonst die Vorgabe.
     */
    public static function inAuswahl(string $feld, int|string $wert, bool $vorgabe): bool
    {
        $request = request();
        if (! $request->hasSession() || ! $request->session()->hasOldInput()) {
            return $vorgabe;
        }

        $auswahl = $request->old($feld);
        if (! is_array($auswahl)) {
            return false;
        }

        foreach ($auswahl as $eintrag) {
            if (self::istText($eintrag) && (string) $eintrag === (string) $wert) {
                return true;
            }
        }

        return false;
    }

    private static function istText(mixed $wert): bool
    {
        return is_scalar($wert) || $wert instanceof Stringable;
    }
}
