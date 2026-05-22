<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use ITHilbert\UserAuth\Entities\UserAuthAuditLog;
use ITHilbert\UserAuth\Listeners\LogAuthenticationAttempt;
use stdClass;
use Tests\TestCase;

final class AuditLogListenerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('user_auth_audit_logs');
        Schema::create('user_auth_audit_logs', function ($t) {
            $t->bigIncrements('id');
            $t->bigInteger('user_id')->nullable();
            $t->string('email')->nullable();
            $t->string('event');
            $t->string('ip_address')->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamps();
        });

        config()->set('userauth.audit_log_enabled', true);
    }

    private function listener(): LogAuthenticationAttempt
    {
        $request = Request::create('/login', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.42',
            'HTTP_USER_AGENT' => 'TestRunner/1.0',
        ]);

        return new LogAuthenticationAttempt($request);
    }

    public function test_login_event_is_logged(): void
    {
        $user = new stdClass();
        $user->id = 7;
        $user->email = 'admin@example.com';

        $event = new Login('web', $user, false);
        $this->listener()->handle($event);

        $this->assertDatabaseHas('user_auth_audit_logs', [
            'event' => 'login',
            'user_id' => 7,
            'email' => 'admin@example.com',
            'ip_address' => '203.0.113.42',
            'user_agent' => 'TestRunner/1.0',
        ]);
    }

    public function test_failed_event_is_logged_without_user_id(): void
    {
        $event = new Failed('web', null, ['email' => 'attacker@example.com']);
        $this->listener()->handle($event);

        $this->assertDatabaseHas('user_auth_audit_logs', [
            'event' => 'failed',
            'user_id' => null,
            'email' => 'attacker@example.com',
        ]);
    }

    public function test_logout_event_is_logged(): void
    {
        $user = new stdClass();
        $user->id = 7;
        $user->email = 'admin@example.com';

        $event = new Logout('web', $user);
        $this->listener()->handle($event);

        $this->assertDatabaseHas('user_auth_audit_logs', [
            'event' => 'logout',
            'user_id' => 7,
        ]);
    }

    public function test_nothing_is_logged_when_audit_disabled(): void
    {
        config()->set('userauth.audit_log_enabled', false);

        $event = new Failed('web', null, ['email' => 'spam@example.com']);
        $this->listener()->handle($event);

        $this->assertSame(0, UserAuthAuditLog::count());
    }
}
