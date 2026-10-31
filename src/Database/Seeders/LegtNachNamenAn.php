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
     * Zwei Durchgänge: erst alle fehlenden Einträge mit freier Wunsch-id, danach die mit
     * belegter Wunsch-id — mit einer id oberhalb der höchsten vorhandenen und aller Wunsch-ids,
     * so dass sie keine Wunsch-id eines anderen Paket-Eintrags belegt.
     *
     * @param  array<int, array<string, mixed>>  $zeilen  Jede Zeile enthält ihre Wunsch-id.
     */
    protected function legeFehlendeAn(string $tabelle, string $namensSpalte, array $zeilen): void
    {
        $fehlend = array_values(array_filter(
            $zeilen,
            fn (array $zeile): bool => ! DB::table($tabelle)->where($namensSpalte, $zeile[$namensSpalte])->exists()
        ));

        $ausweichend = [];
        foreach ($fehlend as $zeile) {
            if (isset($zeile['id']) && DB::table($tabelle)->where('id', $zeile['id'])->exists()) {
                $ausweichend[] = $zeile;

                continue;
            }

            DB::table($tabelle)->insert($zeile);
        }

        if ($ausweichend === []) {
            return;
        }

        $naechste = max(
            (int) DB::table($tabelle)->max('id'),
            ...array_map(fn (array $zeile): int => (int) $zeile['id'], $zeilen)
        ) + 1;

        foreach ($ausweichend as $zeile) {
            $zeile['id'] = $naechste++;
            DB::table($tabelle)->insert($zeile);
        }
    }
}
