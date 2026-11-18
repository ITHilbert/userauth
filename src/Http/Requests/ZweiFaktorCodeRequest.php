<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use ITHilbert\UserAuth\Http\Geheimfelder;

final class ZweiFaktorCodeRequest extends FormRequest
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
            'two_factor_code' => ['required', 'string', 'max:20'],
        ];
    }

    public function code(): string
    {
        return preg_replace('/\s+/', '', (string) $this->validated('two_factor_code')) ?? '';
    }

    protected function failedValidation(Validator $validator): void
    {
        Geheimfelder::entfernen($this, $this->container->make('request'));

        parent::failedValidation($validator);
    }
}
