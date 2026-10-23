<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Passwort;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use ITHilbert\UserAuth\Application\Passwort\Contracts\PasswortHistorie;

final class DatenbankPasswortHistorie implements PasswortHistorie
{
    private const TABELLE = 'user_password_histories';

    public function __construct(private readonly ConnectionInterface $db) {}

    public function letzteHashes(int|string $nutzerId, int $anzahl): array
    {
        return $this->db->table(self::TABELLE)
            ->where('user_id', $nutzerId)
            ->latest()
            ->take($anzahl)
            ->pluck('password')
            ->map(static fn (mixed $hash): string => (string) $hash)
            ->values()
            ->all();
    }

    public function merken(int|string $nutzerId, string $hash, CarbonImmutable $zeitpunkt): void
    {
        $this->db->table(self::TABELLE)->insert([
            'user_id' => $nutzerId,
            'password' => $hash,
            'created_at' => $zeitpunkt,
            'updated_at' => $zeitpunkt,
        ]);
    }
}
