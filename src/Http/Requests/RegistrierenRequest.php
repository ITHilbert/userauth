<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegistrierenRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            ...NeuesPasswortRegeln::regeln(),
        ];
    }

    /**
     * Name und E-Mail ohne führende/folgende Leerzeichen, auch ohne die Middleware TrimStrings des Hosts.
     */
    protected function prepareForValidation(): void
    {
        $getrimmt = [];
        foreach (['name', 'email'] as $feld) {
            $wert = $this->input($feld);
            if (is_string($wert)) {
                $getrimmt[$feld] = trim($wert);
            }
        }

        $this->merge($getrimmt);
    }
}
