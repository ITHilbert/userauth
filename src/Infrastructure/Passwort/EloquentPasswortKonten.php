<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Passwort;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use ITHilbert\UserAuth\Application\Passwort\Contracts\PasswortKonten;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModelMisconfiguredException;

final class EloquentPasswortKonten implements PasswortKonten
{
    public function __construct(private readonly UserModel $userModel) {}

    /**
     * @throws UserModelMisconfiguredException User-Model ohne `CanResetPassword` (vor jeder Suche, damit
     *                                         bekannte und unbekannte Adressen gleich scheitern)
     */
    public function gespeicherteEmail(string $eingabe): ?string
    {
        $klasse = $this->userModel->className();

        if (! is_subclass_of($klasse, CanResetPassword::class)) {
            throw new UserModelMisconfiguredException("User model class '{$klasse}' must implement ".CanResetPassword::class.' (e.g. extend Illuminate\Foundation\Auth\User) for password resets.');
        }

        /** @var list<string> $treffer */
        $treffer = $this->userModel->query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($eingabe)])
            ->pluck('email')
            ->map(static fn (mixed $email): string => (string) $email)
            ->all();

        if (count($treffer) === 1) {
            return $treffer[0];
        }

        // Mehrere Konten, die sich nur in der Schreibweise unterscheiden: nur die exakte Eingabe ist eindeutig
        return in_array($eingabe, $treffer, true) ? $eingabe : null;
    }

    /**
     * @throws UserModelMisconfiguredException
     */
    public function passwortSpeichern(Authenticatable $nutzer, string $hash, CarbonImmutable $geaendertAm): void
    {
        if (! $nutzer instanceof Model) {
            throw new UserModelMisconfiguredException('User '.$nutzer::class.' is not an Eloquent model; userauth cannot store the password.');
        }

        $nutzer->forceFill([
            $nutzer->getAuthPasswordName() => $hash,
            'password_changed_at' => $geaendertAm,
        ])->save();
    }
}
