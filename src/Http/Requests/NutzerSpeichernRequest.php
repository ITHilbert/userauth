<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validierung für Anlegen (ohne Route-Parameter `id`) und Bearbeiten eines Nutzers.
 * Die Berechtigung prüft die Route-Middleware.
 */
final class NutzerSpeichernRequest extends FormRequest
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
        $nutzerId = $this->route('id');
        $anlegen = $nutzerId === null;
        $neuesPasswort = $anlegen || (bool) $this->input('password');

        $email = ['required', 'email'];
        if ($anlegen) {
            $email[] = 'unique:users';
        } else {
            $email[] = Rule::unique('users', 'email')->ignore($nutzerId);
        }

        $regeln = [
            'email' => $email,
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->whereNull('deleted_at'),
            ],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
        ];

        if ($neuesPasswort) {
            $regeln['password'] = ['required'];
            $regeln['password2'] = ['required', 'same:password'];
        }

        foreach (['name', 'firstname', 'lastname', 'smallname', 'title', 'private_email', 'street', 'postcode', 'city', 'country', 'ustid', 'phone', 'phone2', 'mobile', 'fax', 'skype', 'comment', 'anrede_id', 'signature_rule_id'] as $feld) {
            $regeln[$feld] = ['nullable'];
        }

        $regeln['hourly_rate'] = ['nullable', 'numeric', 'min:0', 'max:999999.99'];
        $regeln['birthday'] = ['nullable', 'date_format:Y-m-d'];

        return $regeln;
    }
}
