<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Rules;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use ITHilbert\UserAuth\Application\Passwort\PasswortWiederverwendung;

/**
 * Validierungsregel „keine Wiederverwendung“ für Hosts; die Prüfung selbst liegt in `PasswortWiederverwendung`.
 */
final class PasswordHistoryRule implements Rule
{
    protected $user;

    /**
     * Create a new rule instance.
     *
     * @param  Authenticatable|null  $user
     * @return void
     */
    public function __construct($user = null)
    {
        $this->user = $user ?? Auth::user();
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Ohne Nutzer oder ohne Text nichts zu vergleichen; den Typ prüft die Regel `string`
        if (! $this->user || ! is_string($value)) {
            return true;
        }

        return ! app(PasswortWiederverwendung::class)->istWiederverwendet(
            $this->user->id,
            (string) $this->user->password,
            $value,
        );
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return (string) trans('userauth::password.bereits_verwendet');
    }
}
