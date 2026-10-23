<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Zurücksetzen mit Token. `pwtoken` muss Text sein (keine Zahl, kein Array), bevor es den Broker erreicht.
 */
final class PasswortZuruecksetzenRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'pwtoken' => ['required', 'string', 'max:255'],
            ...NeuesPasswortRegeln::regeln(),
        ];
    }
}
