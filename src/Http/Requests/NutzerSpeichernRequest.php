<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ITHilbert\UserAuth\Application\Benutzer\GeloeschteEmailAufloesen;

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

    protected function prepareForValidation(): void
    {
        if ($this->has('password2')) {
            $this->merge(['password_confirmation' => $this->input('password2')]);
        }
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
            // Gelöschte Benutzer regelt die Option `userauth.geloeschte_email`; bail: nur eine Meldung je Fall.
            array_unshift($email, 'bail');
            $email[] = Rule::unique('users', 'email')->whereNull('deleted_at');
            $email[] = $this->nichtGesperrtDurchGeloeschtenBenutzer();
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
            // Gleiche Regeln wie beim eigenen Ändern; die Bestätigung kommt im Admin-Formular als `password2`.
            $regeln['password'] = NeuesPasswortRegeln::regeln()['password'];
            $regeln['password2'] = ['required', 'same:password'];
        }

        foreach (['name', 'firstname', 'lastname', 'smallname', 'title', 'private_email', 'street', 'postcode', 'city', 'country', 'ustid', 'phone', 'phone2', 'mobile', 'fax', 'skype', 'comment', 'anrede_id', 'signature_rule_id'] as $feld) {
            $regeln[$feld] = ['nullable'];
        }

        $regeln['hourly_rate'] = ['nullable', 'numeric', 'min:0', 'max:999999.99'];
        $regeln['birthday'] = ['nullable', 'date_format:Y-m-d'];

        return $regeln;
    }

    /**
     * Option `sperren`: Die E-Mail eines gelöschten Benutzers bleibt belegt (Groß-/Kleinschreibung egal).
     *
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    private function nichtGesperrtDurchGeloeschtenBenutzer(): Closure
    {
        $geloeschteEmail = $this->container->make(GeloeschteEmailAufloesen::class);

        return static function (string $attribut, mixed $wert, Closure $fail) use ($geloeschteEmail): void {
            if (is_string($wert) && $geloeschteEmail->istGesperrt($wert)) {
                $fail(trans('userauth::user.email_geloeschter_benutzer'));
            }
        };
    }
}
