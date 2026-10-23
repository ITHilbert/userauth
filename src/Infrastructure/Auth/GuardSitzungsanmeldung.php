<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Auth\Contracts\Sitzungsanmeldung;

final class GuardSitzungsanmeldung implements Sitzungsanmeldung
{
    public function __construct(
        private readonly StandardGuard $standardGuard,
        private readonly Request $request,
    ) {}

    public function nutzer(int|string $nutzerId): ?Authenticatable
    {
        return $this->standardGuard->sessionGuard()->getProvider()->retrieveById($nutzerId);
    }

    public function anmelden(Authenticatable $nutzer, bool $angemeldetBleiben): void
    {
        // SessionGuard::login() erneuert die Sitzungs-ID und zerstört die alte Sitzung (regenerate(true))
        $this->standardGuard->sessionGuard()->login($nutzer, $angemeldetBleiben);
    }

    public function abmelden(): void
    {
        $guard = $this->standardGuard->sessionGuard();

        // Gäste (z. B. mit offener 2FA) nicht über den Guard abmelden: kein Logout-Ereignis ohne Nutzer
        if ($guard->check()) {
            $guard->logout();
        }

        $this->request->session()->invalidate();
        $this->request->session()->regenerateToken();
    }
}
