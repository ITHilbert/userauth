<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Application\Auth\Events\ZweiFaktorCodeAbgelehnt;
use ITHilbert\UserAuth\Entities\UserAuthAuditLog;

final class LogAuthenticationAttempt
{
    /**
     * The request instance.
     *
     * @var Request
     */
    public $request;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        if (! config('userauth.audit_log_enabled')) {
            return;
        }

        $data = [
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($event instanceof Login) {
            $data['event'] = 'login';
            $data['user_id'] = $event->user->id;
            $data['email'] = $event->user->email;
        } elseif ($event instanceof Failed) {
            $data['event'] = 'failed';
            $data['user_id'] = null;
            $data['email'] = isset($event->credentials['email']) ? $event->credentials['email'] : null;
        } elseif ($event instanceof ZweiFaktorCodeAbgelehnt) {
            // Eigenes Ereignis: Passwort war richtig, nur der Code wurde abgelehnt (AK22)
            $data['event'] = 'two_factor_failed';
            $data['user_id'] = $event->user->id;
            $data['email'] = $event->user->email;
        } elseif ($event instanceof Logout) {
            $data['event'] = 'logout';
            $data['user_id'] = $event->user ? $event->user->id : null;
            $data['email'] = $event->user ? $event->user->email : null;
        } else {
            return;
        }

        UserAuthAuditLog::create($data);
    }
}
