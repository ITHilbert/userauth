<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use ITHilbert\UserAuth\Rules\PasswordHistoryRule;
use stdClass;
use Tests\TestCase;

final class PasswordHistoryRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('user_password_histories');
        Schema::create('user_password_histories', function ($t) {
            $t->bigIncrements('id');
            $t->bigInteger('user_id');
            $t->string('password');
            $t->timestamps();
        });

        config()->set('userauth.password_policy.enabled', true);
        config()->set('userauth.password_policy.prevent_reuse_last_passwords', 3);
    }

    private function fakeUser(string $currentPassword): stdClass
    {
        $user = new stdClass();
        $user->id = 1;
        $user->password = Hash::make($currentPassword);

        return $user;
    }

    public function test_passes_when_policy_disabled(): void
    {
        config()->set('userauth.password_policy.enabled', false);

        $rule = new PasswordHistoryRule($this->fakeUser('current'));

        $this->assertTrue($rule->passes('password', 'current'));
    }

    public function test_passes_when_user_is_null(): void
    {
        $rule = new PasswordHistoryRule(null);

        $this->assertTrue($rule->passes('password', 'whatever'));
    }

    public function test_rejects_reuse_of_current_password(): void
    {
        $rule = new PasswordHistoryRule($this->fakeUser('Secret123!'));

        $this->assertFalse($rule->passes('password', 'Secret123!'));
    }

    public function test_rejects_reuse_of_recent_password_in_history(): void
    {
        $user = $this->fakeUser('NowCurrent!');
        DB::table('user_password_histories')->insert([
            ['user_id' => 1, 'password' => Hash::make('OldOne!'), 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)],
            ['user_id' => 1, 'password' => Hash::make('OldTwo!'), 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)],
        ]);

        $rule = new PasswordHistoryRule($user);

        $this->assertFalse($rule->passes('password', 'OldOne!'));
        $this->assertFalse($rule->passes('password', 'OldTwo!'));
    }

    public function test_accepts_completely_new_password(): void
    {
        $user = $this->fakeUser('Current!');
        DB::table('user_password_histories')->insert([
            ['user_id' => 1, 'password' => Hash::make('OldOne!'), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $rule = new PasswordHistoryRule($user);

        $this->assertTrue($rule->passes('password', 'BrandNew!'));
    }

    public function test_passes_when_prevent_reuse_count_is_zero(): void
    {
        config()->set('userauth.password_policy.prevent_reuse_last_passwords', 0);

        $rule = new PasswordHistoryRule($this->fakeUser('Current!'));

        // Auch das aktuelle Passwort wird nicht geprüft, wenn prevent_reuse=0
        $this->assertTrue($rule->passes('password', 'Current!'));
    }
}
