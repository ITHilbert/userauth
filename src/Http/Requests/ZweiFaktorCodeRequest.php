<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
}
