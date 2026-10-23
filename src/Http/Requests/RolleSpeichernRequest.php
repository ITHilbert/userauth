<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ITHilbert\UserAuth\Entities\Role;

/**
 * Validierung für Anlegen (ohne Route-Parameter `id`) und Bearbeiten einer Rolle samt Rechte-Liste.
 * Die Berechtigung prüft die Route-Middleware.
 */
final class RolleSpeichernRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $regeln = [
            'permission' => ['nullable', 'array'],
            'permission.*' => [
                'integer',
                Rule::exists('permissions', 'id')->whereNull('deleted_at'),
            ],
        ];

        $rolleId = $this->route('id');
        $unveraendert = $rolleId !== null
            && $this->input('role_display') !== null
            && $this->input('role_display') !== ''
            && Role::query()->findOrFail($rolleId)->role_display === $this->input('role_display');

        if ($unveraendert) {
            $regeln['role_display'] = ['nullable'];
            $regeln['role'] = ['nullable'];
        } else {
            $regeln['role_display'] = ['required', 'unique:roles'];
            $regeln['role'] = ['required', 'unique:roles'];
        }

        return $regeln;
    }
}
