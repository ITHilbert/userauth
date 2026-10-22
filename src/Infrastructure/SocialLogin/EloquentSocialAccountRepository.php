<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\SocialLogin;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialAccountRepository;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialAccountTakenException;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

final class EloquentSocialAccountRepository implements SocialAccountRepository
{
    private const TABLE = 'user_social_accounts';

    public function __construct(private readonly ConnectionInterface $db) {}

    public function findUserIdByAccount(SocialProvider $provider, string $providerUserId): int|string|null
    {
        return $this->db->table(self::TABLE)
            ->where('provider', $provider->value)
            ->where('provider_user_id', $providerUserId)
            ->value('user_id');
    }

    public function userHasProvider(int|string $userId, SocialProvider $provider): bool
    {
        return $this->db->table(self::TABLE)
            ->where('user_id', $userId)
            ->where('provider', $provider->value)
            ->exists();
    }

    public function link(int|string $userId, SocialIdentity $identity): void
    {
        try {
            $this->db->table(self::TABLE)->insert([
                'user_id' => $userId,
                'provider' => $identity->provider->value,
                'provider_user_id' => $identity->providerUserId,
                'email' => $identity->email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new SocialAccountTakenException;
        }
    }

    public function unlink(int|string $userId, SocialProvider $provider): bool
    {
        return $this->db->table(self::TABLE)
            ->where('user_id', $userId)
            ->where('provider', $provider->value)
            ->delete() > 0;
    }

    public function providersOfUser(int|string $userId): array
    {
        return $this->db->table(self::TABLE)
            ->where('user_id', $userId)
            ->pluck('email', 'provider')
            ->all();
    }
}
