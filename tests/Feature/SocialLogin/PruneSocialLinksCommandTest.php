<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

final class PruneSocialLinksCommandTest extends SocialLoginTestCase
{
    private function insertPending(string $hash, \DateTimeInterface $expiresAt): void
    {
        $user = $this->makeUser(verified: false);

        DB::table('user_pending_social_links')->insert([
            'user_id' => $user->getKey(),
            'provider' => 'google',
            'provider_user_id' => 'sub-'.$hash,
            'token_hash' => str_pad($hash, 64, '0'),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_command_deletes_only_expired_pending_links(): void
    {
        $this->insertPending('a', now()->subMinute());
        $this->insertPending('b', now()->subDays(3));
        $this->insertPending('c', now()->addMinutes(30));

        $this->artisan('userauth:prune-social-links')
            ->expectsOutputToContain('2')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('user_pending_social_links')->count());
        $this->assertDatabaseHas('user_pending_social_links', ['token_hash' => str_pad('c', 64, '0')]);
    }

    public function test_command_succeeds_when_nothing_to_prune(): void
    {
        $this->artisan('userauth:prune-social-links')->assertSuccessful();
    }

    public function test_command_is_scheduled_daily(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'userauth:prune-social-links'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }
}
