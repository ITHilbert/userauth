<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Listeners;

use Illuminate\Http\Request;
use ITHilbert\UserAuth\Domain\Passkey\Events\PasskeysZurueckgesetzt;
use ITHilbert\UserAuth\Entities\UserAuthAuditLog;

/**
 * Audit-Log (wenn aktiv): Passkeys eines Benutzers zurückgesetzt (`passkeys_reset`).
 */
final class LogPasskeyChange
{
    public function __construct(private readonly Request $request) {}

    public function handle(PasskeysZurueckgesetzt $event): void
    {
        if (! config('userauth.audit_log_enabled')) {
            return;
        }

        UserAuthAuditLog::create([
            'user_id' => $event->userId,
            'email' => $event->userEmail,
            'event' => 'passkeys_reset',
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
