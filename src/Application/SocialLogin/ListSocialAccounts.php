<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin;

use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialAccountRepository;

final class ListSocialAccounts
{
    public function __construct(private readonly SocialAccountRepository $accounts) {}

    /**
     * @return array<string, string|null> Provider-Key => hinterlegte E-Mail
     */
    public function handle(int|string $userId): array
    {
        return $this->accounts->providersOfUser($userId);
    }
}
