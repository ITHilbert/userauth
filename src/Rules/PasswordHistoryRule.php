<?php

namespace ITHilbert\UserAuth\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PasswordHistoryRule implements Rule
{
    protected $user;

    /**
     * Create a new rule instance.
     *
     * @param \App\Models\User|null $user
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
        if (!config('userauth.password_policy.enabled', false)) {
            return true;
        }

        if (!$this->user) {
            return true;
        }

        $preventReuseCount = config('userauth.password_policy.prevent_reuse_last_passwords', 3);
        
        if ($preventReuseCount <= 0) {
            return true;
        }

        // Aktuelles Passwort prüfen
        if (Hash::check($value, $this->user->password)) {
            return false;
        }

        // Historie prüfen
        $histories = DB::table('user_password_histories')
            ->where('user_id', $this->user->id)
            ->latest()
            ->take($preventReuseCount)
            ->pluck('password');

        foreach ($histories as $oldHash) {
            if (Hash::check($value, $oldHash)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Das Passwort darf nicht einem der zuletzt verwendeten Passwörter entsprechen.';
    }
}
