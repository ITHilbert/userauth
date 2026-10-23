<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Auth;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use ITHilbert\UserAuth\Application\Auth\Contracts\Anmeldedaten;

/**
 * Prüft Zugangsdaten über den Session-Guard (`validate()`: Timebox und Event `Validated` wie bei `attempt()`),
 * ohne anzumelden. `Attempting`/`Failed` und das Rehash des Passworts entsprechen `SessionGuard::attempt()`.
 */
final class GuardAnmeldedaten implements Anmeldedaten
{
    public function __construct(
        private readonly StandardGuard $standardGuard,
        private readonly Dispatcher $events,
        private readonly Repository $config,
    ) {}

    public function pruefen(#[\SensitiveParameter] array $zugangsdaten, bool $angemeldetBleiben): ?Authenticatable
    {
        $guard = $this->standardGuard->sessionGuard();

        $this->events->dispatch(new Attempting($guard->name, $zugangsdaten, $angemeldetBleiben));

        $gueltig = $guard->validate($zugangsdaten);
        $nutzer = $guard->getLastAttempted();

        if (! $gueltig || $nutzer === null) {
            $this->events->dispatch(new Failed($guard->name, $nutzer, $zugangsdaten));

            return null;
        }

        if ($this->config->get('hashing.rehash_on_login', true)) {
            $guard->getProvider()->rehashPasswordIfRequired($nutzer, $zugangsdaten);
        }

        return $nutzer;
    }
}
