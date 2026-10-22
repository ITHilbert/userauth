<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use ITHilbert\UserAuth\Application\Auth\AuthenticationNextStep;

final readonly class SocialLoginResult
{
    private function __construct(
        public SocialLoginOutcome $outcome,
        public ?AuthenticationNextStep $nextStep,
    ) {}

    public static function loggedIn(AuthenticationNextStep $nextStep): self
    {
        return new self(SocialLoginOutcome::LoggedIn, $nextStep);
    }

    public static function confirmationRequired(): self
    {
        return new self(SocialLoginOutcome::ConfirmationRequired, null);
    }
}
