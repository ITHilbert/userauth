<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Prüft Zugangsdaten (erster Faktor), ohne anzumelden. Meldet Fehlschläge wie ein Anmeldeversuch des Guards
 * (Laravel-Event `Failed`), damit Audit-Log und Host-Listener unverändert greifen.
 */
interface Anmeldedaten
{
    /**
     * @param  array<string, mixed>  $zugangsdaten
     * @return Authenticatable|null Nutzer bei gültigen Zugangsdaten, sonst null
     */
    public function pruefen(#[\SensitiveParameter] array $zugangsdaten, bool $angemeldetBleiben): ?Authenticatable;

    /**
     * Prüft das Passwort gegen genau das Konto mit dem Schlüssel; ohne Konto (null) wird kein Passwort verglichen.
     * Events `Attempting`/`Failed` tragen die eingegebenen Zugangsdaten (Kennung statt Schlüssel).
     *
     * @param  array<string, mixed>  $zugangsdaten  eingegebene Kennung und `password`
     * @return Authenticatable|null Nutzer bei gültigem Passwort, sonst null
     */
    public function kontoPruefen(int|string|null $kontoSchluessel, #[\SensitiveParameter] array $zugangsdaten, bool $angemeldetBleiben): ?Authenticatable;
}
