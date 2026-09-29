<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Traits\Auth;

trait TwoFactorAuthenticatable
{
    public function generateTwoFactorCode(): void
    {
        $this->two_factor_code = random_int(100000, 999999);
        $this->two_factor_expires_at = now()->addMinutes(10);
        $this->save();
    }

    public function resetTwoFactorCode(): void
    {
        $this->two_factor_code = null;
        $this->two_factor_expires_at = null;
        $this->save();
    }
}
