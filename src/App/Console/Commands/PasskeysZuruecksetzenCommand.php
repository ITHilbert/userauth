<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\App\Console\Commands;

use Illuminate\Console\Command;
use ITHilbert\UserAuth\Application\Passkey\Exceptions\PasskeyBenutzerNichtEindeutigException;
use ITHilbert\UserAuth\Application\Passkey\PasskeysZuruecksetzen;

/**
 * Wiederherstellung ohne Zugang: löscht alle Passkeys des aktiven Benutzers mit der E-Mail (AK16).
 */
final class PasskeysZuruecksetzenCommand extends Command
{
    protected $signature = 'userauth:passkeys-zuruecksetzen {email : E-Mail-Adresse des aktiven Benutzers}';

    protected $description = 'Löscht alle Passkeys eines Benutzers (Wiederherstellung, wenn kein Gerät mehr da ist)';

    public function handle(PasskeysZuruecksetzen $zuruecksetzen): int
    {
        try {
            $anzahl = $zuruecksetzen->perEmail((string) $this->argument('email'));
        } catch (PasskeyBenutzerNichtEindeutigException $e) {
            $this->error($e->anzahl === 0
                ? 'Kein aktiver Benutzer mit dieser E-Mail gefunden. Nichts gelöscht.'
                : "Die E-Mail gehört {$e->anzahl} aktiven Benutzern. Nichts gelöscht.");

            return self::FAILURE;
        }

        $this->info("{$anzahl} Passkey(s) gelöscht.");

        return self::SUCCESS;
    }
}
