<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Database\Seeders;

use Illuminate\Support\Facades\DB;

/**
 * Legt Einträge nur an, wenn ihr eindeutiger Name fehlt. Vorhandene Einträge
 * (auch soft-gelöschte) werden weder geändert noch gelöscht.
 */
trait LegtNachNamenAn
{
    /**
     * @param  array<string, mixed>  $zeile  Enthält die Wunsch-id; ist sie belegt, vergibt die DB eine freie.
     */
    protected function legeAnWennNameFehlt(string $tabelle, string $namensSpalte, array $zeile): void
    {
        if (DB::table($tabelle)->where($namensSpalte, $zeile[$namensSpalte])->exists()) {
            return;
        }

        if (isset($zeile['id']) && DB::table($tabelle)->where('id', $zeile['id'])->exists()) {
            unset($zeile['id']);
        }

        DB::table($tabelle)->insert($zeile);
    }
}
