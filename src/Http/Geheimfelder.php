<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http;

use Illuminate\Http\Request;

/**
 * Eingabefelder des Pakets, die nie in die Sitzung (`_old_input`) gelangen dürfen (#1595, #1596).
 *
 * Zwei Wege, damit der Schutz nicht vom gebundenen ExceptionHandler abhängt: `dontFlash` am Handler (falls vorhanden)
 * und das Entfernen der Felder aus der Anfrage vor dem Fehlerwurf.
 */
final class Geheimfelder
{
    public const FELDER = ['aktuelles_passwort', 'pwtoken', 'two_factor_code'];

    /**
     * Entfernt die Geheimfelder aus den übergebenen Anfragen (Handler::invalid() flasht `$request->input()`).
     */
    public static function entfernen(Request ...$anfragen): void
    {
        foreach ($anfragen as $anfrage) {
            foreach (self::FELDER as $feld) {
                $anfrage->request->remove($feld);
                $anfrage->query->remove($feld);
                if ($anfrage->isJson()) {
                    $anfrage->json()->remove($feld);
                }
            }
        }
    }
}
