<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use ITHilbert\UserAuth\Application\Auth\AnmeldeEinstellungen;
use ITHilbert\UserAuth\Domain\Auth\AnmeldeModus;

/**
 * Feld der Kennung je `userauth.anmeldung_per`. Die E-Mail-Format-Prüfung gilt nur im Modus `email`:
 * in den anderen Modi verriete eine Formatmeldung nichts, was die Antwort verbergen soll (AK9).
 */
final class PasswortZuruecksetzenAnfordernRequest extends FormRequest
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
}
