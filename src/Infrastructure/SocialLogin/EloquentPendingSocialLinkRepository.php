<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\SocialLogin;

use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\PendingSocialLinkPruner;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\PendingSocialLinkRepository;
use ITHilbert\UserAuth\Application\SocialLogin\PendingSocialLink;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

final class EloquentPendingSocialLinkRepository implements PendingSocialLinkPruner, PendingSocialLinkRepository
{
    private const TABLE = 'user_pending_social_links';

    public function __construct(private readonly ConnectionInterface $db) {}

    public function replace(int|string $userId, SocialIdentity $identity, string $tokenHash, DateTimeInterface $expiresAt): void
    {
        $this->db->transaction(function () use ($userId, $identity, $tokenHash, $expiresAt): void {
            $this->db->table(self::TABLE)
                ->where('user_id', $userId)
                ->where('provider', $identity->provider->value)
                ->delete();

            $this->db->table(self::TABLE)->insert([
                'user_id' => $userId,
                'provider' => $identity->provider->value,
                'provider_user_id' => $identity->providerUserId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function findValid(string $tokenHash, DateTimeInterface $now): ?PendingSocialLink
    {
        return $this->toPending($this->validQuery($tokenHash, $now)->first());
    }

    public function findValidForUpdate(string $tokenHash, DateTimeInterface $now): ?PendingSocialLink
    {
        return $this->toPending($this->validQuery($tokenHash, $now)->lockForUpdate()->first());
    }

    public function deleteExpired(DateTimeInterface $now): int
    {
        return $this->db->table(self::TABLE)->where('expires_at', '<=', $now)->delete();
    }

    private function validQuery(string $tokenHash, DateTimeInterface $now): Builder
    {
        return $this->db->table(self::TABLE)
            ->where('token_hash', $tokenHash)
            ->where('expires_at', '>', $now);
    }

    private function toPending(?object $row): ?PendingSocialLink
    {
        $provider = $row === null ? null : SocialProvider::tryFrom((string) $row->provider);

        return $provider === null ? null : new PendingSocialLink(
            userId: $row->user_id,
            provider: $provider,
            providerUserId: (string) $row->provider_user_id,
            tokenHash: (string) $row->token_hash,
        );
    }

    public function delete(string $tokenHash): void
    {
        $this->db->table(self::TABLE)->where('token_hash', $tokenHash)->delete();
    }
}
