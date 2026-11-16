<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Auth;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use ITHilbert\UserAuth\Application\Auth\Contracts\Anmeldedaten;

/**
 * Prüft Zugangsdaten über den Session-Guard (`validate()`: Timebox und Event `Validated` wie bei `attempt()`),
 * ohne anzumelden. `Attempting`/`Failed` und das Rehash des Passworts entsprechen `SessionGuard::attempt()`.
 */
final class GuardAnmeldedaten implements Anmeldedaten
{
    /** Schlüssel der Konto-Bedingung in den Guard-Zugangsdaten (enthält kein „password“, sonst filtert der Provider ihn). */
    private const KONTO_BEDINGUNG = 'userauth_konto';

    public function __construct(
        private readonly StandardGuard $standardGuard,
        private readonly Dispatcher $events,
        private readonly Repository $config,
    ) {}

    public function pruefen(#[\SensitiveParameter] array $zugangsdaten, bool $angemeldetBleiben): ?Authenticatable
    {
        return $this->validieren($zugangsdaten, $zugangsdaten, $angemeldetBleiben);
    }

    public function kontoPruefen(int|string|null $kontoSchluessel, #[\SensitiveParameter] array $zugangsdaten, bool $angemeldetBleiben): ?Authenticatable
    {
        // Ohne Konto findet der Provider niemanden: kein Passwortvergleich, dieselbe Timebox wie bei falschem Passwort
        $guardZugangsdaten = [
            self::KONTO_BEDINGUNG => static fn (Builder $query): Builder => $kontoSchluessel === null
                ? $query->whereRaw('1 = 0')
                : $query->whereKey($kontoSchluessel),
            'password' => $zugangsdaten['password'] ?? null,
        ];

        return $this->validieren($guardZugangsdaten, $zugangsdaten, $angemeldetBleiben);
    }

    /**
     * @param  array<string, mixed>  $guardZugangsdaten  an den Guard (Suche + Passwort)
     * @param  array<string, mixed>  $eingabe  in den Events (eingegebene Kennung)
     */
    private function validieren(#[\SensitiveParameter] array $guardZugangsdaten, #[\SensitiveParameter] array $eingabe, bool $angemeldetBleiben): ?Authenticatable
    {
        $guard = $this->standardGuard->sessionGuard();

        $this->events->dispatch(new Attempting($guard->name, $eingabe, $angemeldetBleiben));

        $gueltig = $guard->validate($guardZugangsdaten);
        $nutzer = $guard->getLastAttempted();

        if (! $gueltig || $nutzer === null) {
            $this->events->dispatch(new Failed($guard->name, $nutzer, $eingabe));

            return null;
        }

        if ($this->config->get('hashing.rehash_on_login', true)) {
            $guard->getProvider()->rehashPasswordIfRequired($nutzer, $guardZugangsdaten);
        }

        return $nutzer;
    }
}
