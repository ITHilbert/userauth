<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use ITHilbert\UserAuth\Http\Geheimfelder;

/**
 * Angemeldeter Benutzer bestätigt sich vor „Passkey hinzufügen“ mit seinem aktuellen Passwort
 * (passkey-bestaetigung, AK2, AK4). Die Anmeldung prüft die Route-Middleware.
 */
final class PasskeyBestaetigenRequest extends FormRequest
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
        return ['aktuelles_passwort' => ['bail', 'required', 'string', 'current_password']];
    }

    protected function failedValidation(Validator $validator): void
    {
        Geheimfelder::entfernen($this, $this->container->make('request'));

        parent::failedValidation($validator);
    }
}
