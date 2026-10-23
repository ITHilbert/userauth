<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Angemeldeter Nutzer ändert sein Passwort. Die Anmeldung prüft die Route-Middleware.
 */
final class PasswortAendernRequest extends FormRequest
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
        return NeuesPasswortRegeln::regeln();
    }
}
