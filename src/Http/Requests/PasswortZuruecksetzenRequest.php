<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use ITHilbert\UserAuth\Application\Auth\AnmeldeEinstellungen;
use ITHilbert\UserAuth\Domain\Auth\AnmeldeModus;
use ITHilbert\UserAuth\Http\Geheimfelder;

/**
 * Zurücksetzen mit Token. `pwtoken` muss Text sein (keine Zahl, kein Array), bevor es den Broker erreicht.
 * Die Kennung aus dem Link steht im Feld der Kennung je `userauth.anmeldung_per`; E-Mail-Format nur im Modus `email`.
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
        $modus = $this->modus();

        return [
            $modus->feld() => $modus === AnmeldeModus::Email
                ? ['required', 'string', 'email', 'max:255']
                : ['required', 'string', 'max:255'],
            'pwtoken' => ['required', 'string', 'max:255'],
            ...NeuesPasswortRegeln::regeln(),
        ];
    }

    public function kennung(): string
    {
        return (string) $this->validated($this->modus()->feld());
    }

    private function modus(): AnmeldeModus
    {
        return $this->container->make(AnmeldeEinstellungen::class)->modus();
    }

    protected function failedValidation(Validator $validator): void
    {
        Geheimfelder::entfernen($this, $this->container->make('request'));

        parent::failedValidation($validator);
    }
}
