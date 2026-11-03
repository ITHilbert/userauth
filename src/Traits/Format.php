<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Traits;

trait Format
{
    /**
     * Bildet aus einer Anzeige den internen Namen (Regel AK1; der Browser bildet ihn in js/rechtename.js gleich):
     * Kleinbuchstaben, Umlaute und ß ersetzen, `; : " ' ` ~ < > |` entfernen, Leerraum außen entfernen,
     * Leerraum innen zu genau einem `_`.
     */
    public function formatDisplayToIntern($display): string
    {
        $intern = mb_strtolower((string) $display);
        $intern = strtr($intern, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $intern = str_replace([';', ':', '"', "'", '`', '~', '<', '>', '|'], '', $intern);
        $intern = (string) preg_replace('/^\s+|\s+$/u', '', $intern);

        return (string) preg_replace('/\s+/u', '_', $intern);
    }
}
