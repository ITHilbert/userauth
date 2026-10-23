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
}
