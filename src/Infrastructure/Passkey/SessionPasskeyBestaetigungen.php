<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Passkey;

use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyBestaetigungen;

/**
 * Legt die Bestätigung unter `userauth.passkey_bestaetigt_bis` (Zeitstempel) und `userauth.passkey_bestaetigt_fuer`
 * (Benutzer-ID) in der Sitzung ab. Ohne Sitzung (Konsole) gibt es keine Bestätigung.
 */
final class SessionPasskeyBestaetigungen implements PasskeyBestaetigungen
{
    private const BIS = 'userauth.passkey_bestaetigt_bis';

    private const FUER = 'userauth.passkey_bestaetigt_fuer';

    public function __construct(private readonly Request $request) {}

    public function merken(int|string $userId, int $bis): void
    {
        if (! $this->request->hasSession()) {
            return;
        }

        $this->request->session()->put([self::BIS => $bis, self::FUER => (string) $userId]);
    }

    public function gueltigBis(int|string $userId): ?int
    {
        if (! $this->request->hasSession()) {
            return null;
        }

        $sitzung = $this->request->session();
        $bis = $sitzung->get(self::BIS);
        $fuer = $sitzung->get(self::FUER);

        if (! is_int($bis) || ! is_string($fuer) || ! hash_equals($fuer, (string) $userId)) {
            return null;
        }

        return $bis;
    }

    public function vergessen(): void
    {
        if ($this->request->hasSession()) {
            $this->request->session()->forget([self::BIS, self::FUER]);
        }
    }
}
