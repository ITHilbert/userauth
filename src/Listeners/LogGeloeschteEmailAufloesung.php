<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Listeners;

use Illuminate\Http\Request;
use ITHilbert\UserAuth\Domain\Benutzer\Events\BenutzerEndgueltigGeloescht;
use ITHilbert\UserAuth\Domain\Benutzer\Events\BenutzerReaktiviert;
use ITHilbert\UserAuth\Entities\UserAuthAuditLog;

/**
 * Audit-Log: gelöschter Benutzer wiederhergestellt (`user_reactivated`) oder endgültig entfernt (`user_purged`).
 */
final class LogGeloeschteEmailAufloesung
{
    public function __construct(private readonly Request $request) {}

    public function handle(BenutzerReaktiviert|BenutzerEndgueltigGeloescht $event): void
    {
        if (! config('userauth.audit_log_enabled')) {
            return;
        }

        UserAuthAuditLog::create([
            'user_id' => $event->userId,
            'email' => $event->userEmail,
            'event' => $event instanceof BenutzerReaktiviert ? 'user_reactivated' : 'user_purged',
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
