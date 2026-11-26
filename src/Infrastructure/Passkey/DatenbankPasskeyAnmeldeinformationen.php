<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Passkey;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use ITHilbert\UserAuth\Application\Passkey\Contracts\PasskeyAnmeldeinformationen;
use ITHilbert\UserAuth\Application\Passkey\GespeicherterPasskey;
use ITHilbert\UserAuth\Application\Passkey\NeuerPasskey;
use ITHilbert\UserAuth\Application\Passkey\PasskeyEintrag;

/**
 * Passkeys in der Tabelle `user_passkeys`.
 */
final class DatenbankPasskeyAnmeldeinformationen implements PasskeyAnmeldeinformationen
{
    private const TABELLE = 'user_passkeys';

    public function __construct(private readonly ConnectionInterface $db) {}

    public function zurKennung(string $kennung): ?GespeicherterPasskey
    {
        $zeile = $this->tabelle()->where('credential_id', $kennung)->first(['id', 'user_id', 'credential_source']);

        return $zeile === null ? null : new GespeicherterPasskey((int) $zeile->id, $zeile->user_id, (string) $zeile->credential_source);
    }

    public function kennungVergeben(string $kennung): bool
    {
        return $this->tabelle()->where('credential_id', $kennung)->exists();
    }

    public function kennungenDesBenutzers(int|string $userId): array
    {
        return array_values(array_map('strval', $this->tabelle()->where('user_id', $userId)->orderBy('id')->pluck('credential_id')->all()));
    }

    public function desBenutzers(int|string $userId): array
    {
        $eintraege = [];

        foreach ($this->tabelle()->where('user_id', $userId)->orderBy('id')->get(['id', 'name', 'created_at', 'last_used_at']) as $zeile) {
            $eintraege[] = new PasskeyEintrag(
                (int) $zeile->id,
                (string) $zeile->name,
                $this->zeitpunkt($zeile->created_at),
                $this->zeitpunkt($zeile->last_used_at),
            );
        }

        return $eintraege;
    }

    public function anzahl(int|string $userId): int
    {
        return $this->tabelle()->where('user_id', $userId)->count();
    }

    public function speichern(int|string $userId, string $name, NeuerPasskey $passkey, CarbonImmutable $jetzt): bool
    {
        try {
            $this->tabelle()->insert([
                'user_id' => $userId,
                'name' => $name,
                'credential_id' => $passkey->kennung,
                'credential_source' => $passkey->quelle,
                'last_used_at' => null,
                'created_at' => $jetzt,
                'updated_at' => $jetzt,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function nachAnmeldung(int $passkeyId, string $quelle, CarbonImmutable $jetzt): void
    {
        $this->tabelle()->where('id', $passkeyId)->update([
            'credential_source' => $quelle,
            'last_used_at' => $jetzt,
            'updated_at' => $jetzt,
        ]);
    }

    public function loeschen(int|string $userId, int $passkeyId): bool
    {
        return $this->tabelle()->where('id', $passkeyId)->where('user_id', $userId)->delete() > 0;
    }

    public function alleLoeschen(int|string $userId): int
    {
        return $this->tabelle()->where('user_id', $userId)->delete();
    }

    private function tabelle(): Builder
    {
        return $this->db->table(self::TABELLE);
    }

    private function zeitpunkt(mixed $wert): ?CarbonImmutable
    {
        return is_string($wert) && $wert !== '' ? CarbonImmutable::parse($wert) : null;
    }
}
