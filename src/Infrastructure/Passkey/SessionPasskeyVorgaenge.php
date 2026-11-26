<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Passkey;

use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyVorgaenge;
use ITHilbert\UserAuth\Application\Passkey\PasskeyVorgang;
use ITHilbert\UserAuth\Application\Passkey\PasskeyVorgangsArt;

/**
 * Legt je Art einen Passkey-Vorgang unter `userauth.passkey.<art>` in der Sitzung ab.
 */
final class SessionPasskeyVorgaenge implements PasskeyVorgaenge
{
    private const PRAEFIX = 'userauth.passkey.';

    public function __construct(private readonly Request $request) {}

    public function merken(PasskeyVorgangsArt $art, PasskeyVorgang $vorgang): void
    {
        $this->request->session()->put(self::PRAEFIX.$art->value, [
            'zustand' => $vorgang->zustand,
            'erstellt_am' => $vorgang->erstelltAm,
            'benutzer' => $vorgang->benutzer,
        ]);
    }

    public function entnehmen(PasskeyVorgangsArt $art): ?PasskeyVorgang
    {
        $daten = $this->request->session()->pull(self::PRAEFIX.$art->value);

        if (! is_array($daten) || ! is_string($daten['zustand'] ?? null) || ! is_int($daten['erstellt_am'] ?? null)) {
            return null;
        }

        $benutzer = $daten['benutzer'] ?? null;

        return new PasskeyVorgang($daten['zustand'], $daten['erstellt_am'], is_string($benutzer) ? $benutzer : null);
    }
}
