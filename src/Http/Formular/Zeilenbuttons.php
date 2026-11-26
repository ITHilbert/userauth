<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Formular;

use Illuminate\Support\Facades\Blade;

/**
 * Edit- und Löschen-Knöpfe der DataTable-Spalte `action` (Benutzer, Rollen, Rechte) als Blade-Components des Kits.
 */
final class Zeilenbuttons
{
    /**
     * @param  string|null  $bearbeitenRoute  null: kein Edit-Knopf (Recht fehlt)
     * @param  int|string|null  $loeschenId  null: kein Löschen-Knopf (Recht fehlt)
     */
    public static function render(?string $bearbeitenRoute, int|string|null $loeschenId): string
    {
        $ausgabe = '<div style="white-space: nowrap;">';
        if ($bearbeitenRoute !== null) {
            $ausgabe .= Blade::render('<x-kit::button-edit :route="$route" />', ['route' => $bearbeitenRoute]);
        }
        if ($loeschenId !== null) {
            $ausgabe .= Blade::render('<x-kit::button-delete :value="$id" />', ['id' => $loeschenId]);
        }

        return $ausgabe.'</div>';
    }
}
