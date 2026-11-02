<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Formular;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use ITHilbert\UserAuth\Entities\Permission;
use ITHilbert\UserAuth\Entities\PermissionGroup;

/**
 * Prüft vor dem Speichern einer Rechtegruppe, dass der formatierte Gruppenname und bei einer CRUD-Gruppe
 * die abgeleiteten Rechtenamen frei sind (auch soft-gelöschte; die Datenbank entscheidet über "gleich").
 */
final class GruppenNamenPruefung
{
    private const CRUD = ['create', 'read', 'edit', 'delete'];

    /**
     * @param  int|null  $gruppeId  die bearbeitete Gruppe; sie und ihre aktiven Rechte zählen nicht als Kollision
     *
     * @throws ValidationException
     */
    public function pruefen(string $name, bool $crud, ?int $gruppeId = null): void
    {
        $gruppeBelegt = PermissionGroup::withTrashed()
            ->where('group_name', $name)
            ->when($gruppeId !== null, fn ($abfrage) => $abfrage->where('id', '!=', $gruppeId))
            ->exists();

        $rechteBelegt = $crud && Permission::withTrashed()
            ->whereIn('permission', array_map(fn (string $art): string => $name.'_'.$art, self::CRUD))
            ->when($gruppeId !== null, fn ($abfrage) => $abfrage->whereNotIn(
                'id',
                Permission::where('group_id', $gruppeId)->select('id')
            ))
            ->exists();

        if ($gruppeBelegt || $rechteBelegt) {
            throw $this->fehler();
        }
    }

    /**
     * Führt das Speichern aus; eine Unique-Verletzung (Rennen) wird zum Validierungsfehler am Feld `group_name`.
     *
     * @throws ValidationException
     */
    public function speichern(Closure $speichern): void
    {
        try {
            $speichern();
        } catch (UniqueConstraintViolationException) {
            throw $this->fehler();
        }
    }

    private function fehler(): ValidationException
    {
        return ValidationException::withMessages(['group_name' => __('userauth::permission.NameVergeben')]);
    }
}
