<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\SocialLogin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialUserRepository;
use ITHilbert\UserAuth\Application\SocialLogin\LocalUser;
use ITHilbert\UserAuth\Entities\Role;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;
use ITHilbert\UserAuth\Infrastructure\Registrierung\EloquentRegistrierungsKonten;

final class EloquentSocialUserRepository implements SocialUserRepository
{
    public function __construct(private readonly UserModel $userModel) {}

    public function findActiveById(int|string $id): ?LocalUser
    {
        $user = $this->query()->whereKey($id)->first();

        return $user === null ? null : $this->toLocalUser($user);
    }

    public function findActiveByEmail(string $email): ?LocalUser
    {
        $user = $this->query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

        return $user === null ? null : $this->toLocalUser($user);
    }

    public function existsDeletedWithEmail(string $email): bool
    {
        return $this->query()
            ->withTrashed()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->exists();
    }

    public function createVerified(?string $name, string $email, string $roleKey): LocalUser
    {
        $roleId = Role::query()->where('role', $roleKey)->value('id')
            ?? throw new SocialLoginMisconfiguredException("Role '{$roleKey}' (userauth.social.default_role) does not exist.");

        $user = $this->userModel->newInstance();
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ])->save();

        return $this->toLocalUser($user);
    }

    /**
     * Konto mit „Bestätigung offen“ aus der Selbstregistrierung: Das dort gesetzte Passwort und Angemeldet-bleiben-
     * Tokens verfallen im selben Schritt (AK17 – sonst übernimmt, wer die Adresse vorab registriert hat, das Konto
     * des echten Inhabers). Andere Konten behalten ihr Passwort.
     */
    public function markEmailVerified(int|string $id): void
    {
        $offen = $this->query()->whereKey($id)->whereNull('email_verified_at')->lockForUpdate()->first();

        if ($offen === null) {
            return;
        }

        $aenderungen = ['email_verified_at' => now()];

        // getAttributes(): Fehlt die Spalte (Migration noch nicht gelaufen), gilt das Konto als nicht registriert
        if ((bool) ($offen->getAttributes()[EloquentRegistrierungsKonten::SPALTE] ?? false)) {
            $aenderungen['password'] = Hash::make(Str::random(64));
            $aenderungen['remember_token'] = Str::random(60);
        }

        $this->query()->whereKey($id)->whereNull('email_verified_at')->update($aenderungen);
    }

    /**
     * @return Builder<Model>
     */
    private function query(): Builder
    {
        return $this->userModel->query();
    }

    private function toLocalUser(Model $user): LocalUser
    {
        return new LocalUser(
            id: $user->getKey(),
            email: (string) $user->getAttribute('email'),
            name: $user->getAttribute('name'),
            emailVerified: $user->getAttribute('email_verified_at') !== null,
        );
    }
}
