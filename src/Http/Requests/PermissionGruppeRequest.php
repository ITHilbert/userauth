<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Eingaberegeln für Anlegen und Bearbeiten einer Rechtegruppe. Die Eindeutigkeit des Gruppennamens
 * und der abgeleiteten Rechtenamen prüft `GruppenNamenPruefung` (auf dem formatierten Namen).
 * Die Berechtigung regeln die Routen-Middleware.
 */
final class PermissionGruppeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $anlegen = $this->route('id') === null;

        return [
            'group_display' => ['bail', 'required', 'string', ...($anlegen ? ['unique:permissions_groups'] : [])],
            'group_name' => ['bail', 'required', 'string'],
        ];
    }
}
