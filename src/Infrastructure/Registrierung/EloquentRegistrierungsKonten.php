<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Registrierung;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use ITHilbert\UserAuth\Application\Registrierung\Contracts\RegistrierungsKonten;
use ITHilbert\UserAuth\Application\Registrierung\RegistriertesKonto;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;

/**
 * Konten der Selbstregistrierung in `users`. Kennzeichen ist die Spalte `self_registered`
 * (Migration 2026_11_03_000000); „Bestätigung offen“ = `self_registered` und `email_verified_at` leer.
 */
final class EloquentRegistrierungsKonten implements RegistrierungsKonten
{
    private const SPALTE = 'self_registered';

    public function __construct(private readonly UserModel $userModel) {}

    public function emailVergeben(string $email): bool
    {
        // Ohne Scopes: auch soft-gelöschte Konten sperren die Adresse
        return $this->userModel->newInstance()->newQueryWithoutScopes()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->exists();
    }

    public function anlegen(string $name, string $email, string $passwortHash, ?int $rolleId, CarbonImmutable $jetzt): ?RegistriertesKonto
    {
        $nutzer = $this->userModel->newInstance();
        $nutzer->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $passwortHash,
            'password_changed_at' => $jetzt,
            'role_id' => $rolleId,
            'email_verified_at' => null,
            self::SPALTE => true,
        ]);

        try {
            $nutzer->save();
        } catch (UniqueConstraintViolationException) {
            // Gleichzeitige Registrierung derselben Adresse: wie eine vergebene E-Mail behandeln
            return null;
        }

        return $this->konto($nutzer);
    }

    public function offenesKontoZurEmail(string $email): ?RegistriertesKonto
    {
        $treffer = $this->query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->where(self::SPALTE, true)
            ->whereNull('email_verified_at')
            ->get();

        // Mehrere Konten, die sich nur in der Schreibweise unterscheiden: nur die exakte Eingabe ist eindeutig
        $nutzer = $treffer->count() === 1 ? $treffer->first() : $treffer->first(static fn (Model $n): bool => $n->getAttribute('email') === $email);

        return $nutzer === null ? null : $this->konto($nutzer);
    }

    public function aktivesKonto(string $id): ?RegistriertesKonto
    {
        if (! ctype_digit($id)) {
            return null;
        }

        $nutzer = $this->query()->whereKey($id)->first();

        return $nutzer === null ? null : $this->konto($nutzer);
    }

    public function alsBestaetigtMarkieren(int|string $id, CarbonImmutable $jetzt): bool
    {
        return $this->query()->whereKey($id)->whereNull('email_verified_at')->update(['email_verified_at' => $jetzt]) > 0;
    }

    public function bestaetigungOffen(Authenticatable $nutzer): bool
    {
        if (! $nutzer instanceof Model) {
            return false;
        }

        // getAttributes(): Fehlt die Spalte (Migration noch nicht gelaufen), gilt das Konto als nicht registriert
        $attribute = $nutzer->getAttributes();

        return (bool) ($attribute[self::SPALTE] ?? false) && ($attribute['email_verified_at'] ?? null) === null;
    }

    /**
     * @return Builder<Model>
     */
    private function query(): Builder
    {
        return $this->userModel->query();
    }

    private function konto(Model $nutzer): RegistriertesKonto
    {
        return new RegistriertesKonto(
            id: $nutzer->getKey(),
            email: (string) $nutzer->getAttribute('email'),
            emailBestaetigt: $nutzer->getAttribute('email_verified_at') !== null,
        );
    }
}
