<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use ITHilbert\UserAuth\Domain\Benutzer\Events\BenutzerReaktiviert;
use ITHilbert\UserAuth\Domain\SocialLogin\Events\SocialAccountLinked;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;
use ITHilbert\UserAuth\Entities\UserAuthAuditLog;
use ITHilbert\UserAuth\Listeners\LogAuthenticationAttempt;
use ITHilbert\UserAuth\Listeners\LogGeloeschteEmailAufloesung;
use ITHilbert\UserAuth\Listeners\LogSocialAccountChange;
use ITHilbert\UserAuth\Tests\TestCase;
use stdClass;

/**
 * Regression #1588: Audit-Log darf unter Model::shouldBeStrict() nie werfen.
 */
final class AuditLogStrictModeTest extends TestCase
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
        Model::shouldBeStrict();
    }

    protected function tearDown(): void
    {
        Model::shouldBeStrict(false);

        parent::tearDown();
    }

    private function request(): Request
    {
        return Request::create('/login', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.42',
            'HTTP_USER_AGENT' => 'TestRunner/1.0',
        ]);
    }

    private function nutzer(): stdClass
    {
        $user = new stdClass;
        $user->id = 7;
        $user->email = 'admin@example.com';

        return $user;
    }

    // Regression #1588: Login, Failed, Logout unter Strict-Modus
    public function test_anmeldeereignisse_werfen_unter_strict_modus_nicht(): void
    {
        $listener = new LogAuthenticationAttempt($this->request());

        $listener->handle(new Login('web', $this->nutzer(), false));
        $listener->handle(new Failed('web', null, ['email' => 'x@example.com']));
        $listener->handle(new Logout('web', $this->nutzer()));

        $this->assertSame(3, UserAuthAuditLog::count());
        foreach (['login', 'failed', 'logout'] as $event) {
            $log = UserAuthAuditLog::where('event', $event)->first();
            $this->assertNotNull($log, $event);
            $this->assertNotNull($log->created_at, $event);
            $this->assertSame('203.0.113.42', $log->ip_address);
        }
    }

    // Regression #1588: Social-Link unter Strict-Modus
    public function test_social_account_change_wirft_unter_strict_modus_nicht(): void
    {
        $event = new SocialAccountLinked(7, 'admin@example.com', SocialProvider::cases()[0]);

        (new LogSocialAccountChange($this->request()))->handle($event);

        $log = UserAuthAuditLog::where('event', 'social_linked')->first();
        $this->assertNotNull($log);
        $this->assertSame(7, (int) $log->user_id);
        $this->assertSame('admin@example.com', $log->email);
        $this->assertNotNull($log->created_at);
    }

    // Regression #1588: Reaktivierung unter Strict-Modus
    public function test_reaktivierung_wirft_unter_strict_modus_nicht(): void
    {
        (new LogGeloeschteEmailAufloesung($this->request()))
            ->handle(new BenutzerReaktiviert(7, 'admin@example.com'));

        $log = UserAuthAuditLog::where('event', 'user_reactivated')->first();
        $this->assertNotNull($log);
        $this->assertSame('admin@example.com', $log->email);
        $this->assertNotNull($log->created_at);
    }
}
