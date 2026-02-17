<?php

namespace ITHilbert\UserAuth\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use ITHilbert\UserAuth\Entities\UserAuthAuditLog;

class LogAuthenticationAttempt
{
    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    public $request;

    /**
     * Create the event listener.
     *
     * @param  \Illuminate\Http\Request  $request
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
        if (!config('userauth.audit_log_enabled')) {
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
