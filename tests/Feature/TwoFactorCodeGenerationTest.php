<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Support\Carbon;
use ITHilbert\UserAuth\Traits\Auth\TwoFactorAuthenticatable;
use ReflectionClass;
use ITHilbert\UserAuth\Tests\TestCase;

final class TwoFactorCodeGenerationTest extends TestCase
{
    private function makeUser(): object
    {
        return new class
        {
            use TwoFactorAuthenticatable;

            public mixed $two_factor_code = null;

            public mixed $two_factor_expires_at = null;

            public int $saveCalls = 0;

            public function save(): bool
            {
                $this->saveCalls++;

                return true;
            }
        };
    }

    public function test_generates_six_digit_code_and_expiry(): void
    {
        Carbon::setTestNow('2026-09-29 12:00:00');
        $user = $this->makeUser();

        $user->generateTwoFactorCode();

        $this->assertMatchesRegularExpression('/^[1-9]\d{5}$/', (string) $user->two_factor_code);
        $this->assertTrue(now()->addMinutes(10)->equalTo($user->two_factor_expires_at));
        $this->assertSame(1, $user->saveCalls);

        Carbon::setTestNow();
    }

    public function test_codes_stay_within_range_over_many_runs(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 500; $i++) {
            $user->generateTwoFactorCode();
            $this->assertGreaterThanOrEqual(100000, (int) $user->two_factor_code);
            $this->assertLessThanOrEqual(999999, (int) $user->two_factor_code);
        }
    }

    public function test_uses_cryptographically_secure_random_generator(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(TwoFactorAuthenticatable::class))->getFileName());

        $this->assertStringContainsString('random_int(', $source);
        $this->assertDoesNotMatchRegularExpression('/(?<![_a-z])(mt_)?rand\s*\(/', $source);
    }

    public function test_reset_clears_code_and_expiry(): void
    {
        $user = $this->makeUser();
        $user->generateTwoFactorCode();

        $user->resetTwoFactorCode();

        $this->assertNull($user->two_factor_code);
        $this->assertNull($user->two_factor_expires_at);
    }
}
