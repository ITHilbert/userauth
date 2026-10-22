<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\SocialLogin;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialUserRepository;
use ITHilbert\UserAuth\Application\SocialLogin\LocalUser;
use ITHilbert\UserAuth\Entities\Role;

final class EloquentSocialUserRepository implements SocialUserRepository
{
    public function __construct(private readonly Config $config) {}

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

        $user = $this->newModel();
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ])->save();

        return $this->toLocalUser($user);
    }

    public function markEmailVerified(int|string $id): void
    {
        $this->query()->whereKey($id)->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    /**
     * @return class-string<Model>
     */
    private function modelClass(): string
    {
        return (string) $this->config->get('auth.providers.users.model');
    }

    private function newModel(): Model
    {
        $class = $this->modelClass();

        return new $class;
    }

    /**
     * @return Builder<Model>
     */
    private function query(): Builder
    {
        return $this->modelClass()::query();
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
