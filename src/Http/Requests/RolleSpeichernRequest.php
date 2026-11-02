<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ITHilbert\UserAuth\Entities\Permission;
use ITHilbert\UserAuth\Traits\Format;

/**
 * Validierung für Anlegen (ohne Route-Parameter `id`) und Bearbeiten einer Rolle samt Rechte-Liste.
 * Zuweisbar sind nur Rechte, die `Permission::fuerRollenformular()` liefert.
 * Die Berechtigung prüft die Route-Middleware.
 */
final class RolleSpeichernRequest extends FormRequest
{
    use Format;

    protected function prepareForValidation(): void
    {
        $role = $this->input('role');

        if (is_string($role)) {
            $this->merge(['role' => $this->formatDisplayToIntern($role)]);
        }
    }

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
                // nur nicht gelöschte Rechte außerhalb der im Rollenformular verborgenen Gruppen
                Rule::in(Permission::query()->fuerRollenformular()->pluck('id')->all()),
            ],
        ];

        $ignorieren = $this->route('id');

        $regeln['role_display'] = ['required', 'string', 'max:255', Rule::unique('roles')->ignore($ignorieren)];
        $regeln['role'] = ['required', 'string', 'max:255', Rule::unique('roles')->ignore($ignorieren)];

        return $regeln;
    }

    /**
     * Beschriftungen wie im Rollenformular.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role_display' => __('userauth::role.role'),
            'role' => __('userauth::role.role_intern'),
            'permission' => __('userauth::permission.permission'),
            'permission.*' => __('userauth::permission.permission'),
        ];
    }
}
