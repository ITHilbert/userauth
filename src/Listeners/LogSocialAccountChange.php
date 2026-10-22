<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Listeners;

use Illuminate\Http\Request;
use ITHilbert\UserAuth\Domain\SocialLogin\Events\SocialAccountLinked;
use ITHilbert\UserAuth\Domain\SocialLogin\Events\SocialAccountUnlinked;
use ITHilbert\UserAuth\Entities\UserAuthAuditLog;

final class LogSocialAccountChange
{
    public function __construct(private readonly Request $request) {}

    public function handle(SocialAccountLinked|SocialAccountUnlinked $event): void
    {
        if (! config('userauth.audit_log_enabled')) {
            return;
        }

        UserAuthAuditLog::create([
            'user_id' => $event->userId,
            'email' => $event->userEmail,
            'event' => $event instanceof SocialAccountLinked ? 'social_linked' : 'social_unlinked',
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
