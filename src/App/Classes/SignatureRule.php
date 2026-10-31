<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\App\Classes;

class SignatureRule
{
    /**
     * Zeichnungsregeln aus der Config als Optionen für `<x-kit::combobox>` (`cbKey`/`cbCaption`).
     *
     * @return list<array{cbKey: mixed, cbCaption: mixed}>
     */
    public static function comboboxOptionen(): array
    {
        $optionen = [];
        foreach ((array) config('userauth.signature_rules') as $eintrag) {
            $optionen[] = ['cbKey' => $eintrag['id'] ?? null, 'cbCaption' => $eintrag['meaning'] ?? null];
        }

        return $optionen;
    }

    /**
     * Gibt eine ComboBox zurück
     *
     * @param  int  $id
     * @param  string  $name
     * @return void
     */
    public static function getComboBox($id = 1, $name = 'signature_rule_id')
    {
        $daten = config('userauth.signature_rules');

        $ausgabe = '<select name="'.$name.'" id="'.$name.'" class="form-control">';
        foreach ($daten as $row) {
            if ($row['id'] == $id) {
                $ausgabe .= '<option value="'.$row['id'].'" selected>'.$row['meaning'].'</option>';
            } else {
                $ausgabe .= '<option value="'.$row['id'].'">'.$row['meaning'].'</option>';
            }
        }
        $ausgabe .= '</select>';

        return $ausgabe;
    }

    /**
     * Liefert den Datensatz zurück
     *
     * @param [int] $id
     * @return void
     */
    public static function get(int $id)
    {
        $daten = config('userauth.signature_rules');

        return $daten[$id - 1];
    }

    /**
     * Gibt die Status Bezeichnung zur ID zurück
     *
     * @param [type] $statusID
     * @return void
     */
    public static function getSignatureRule(int $id)
    {
        $daten = config('userauth.signature_rules');

        return $daten[$id - 1]['signature_rule'];
    }

    /**
     * Gibt die Status Bezeichnung zur ID zurück
     *
     * @param [type] $statusID
     * @return void
     */
    public static function getMeaning(int $id)
    {
        $daten = config('userauth.signature_rules');

        return $daten[$id - 1]['meaning'];
    }
}
