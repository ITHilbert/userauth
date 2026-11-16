<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Passwort;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use ITHilbert\UserAuth\Application\Auth\Contracts\KontoZurKennung;
use ITHilbert\UserAuth\Application\Passwort\Contracts\PasswortKonten;
use ITHilbert\UserAuth\Application\Passwort\ResetKonto;
use ITHilbert\UserAuth\Domain\Auth\AnmeldeModus;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModelMisconfiguredException;

final class EloquentPasswortKonten implements PasswortKonten
{
    private const BENUTZERNAME = 'benutzername';

    public function __construct(
        private readonly UserModel $userModel,
        private readonly KontoZurKennung $kontoZurKennung,
    ) {}

    /**
     * @throws UserModelMisconfiguredException User-Model ohne `CanResetPassword` (vor jeder Suche, damit
     *                                         bekannte und unbekannte Adressen gleich scheitern)
     */
    public function gespeicherteEmail(string $eingabe): ?string
    {
        $this->resetfaehigSicherstellen();

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
     * Kennung im Link: der Benutzername, bei einem Konto ohne Benutzername (Modus `beides`) die eindeutige E-Mail.
     *
     * @throws UserModelMisconfiguredException
     */
    public function zurKennung(AnmeldeModus $modus, string $kennung): ?ResetKonto
    {
        $this->resetfaehigSicherstellen();

        $kontoId = $this->kontoZurKennung->finden($modus, $kennung);

        if ($kontoId === null) {
            return null;
        }

        $nutzer = $this->userModel->query()->whereKey($kontoId)->first();

        if (! $nutzer instanceof Authenticatable || ! $nutzer instanceof CanResetPassword) {
            return null;
        }

        $email = trim((string) $nutzer->getEmailForPasswordReset());
        $benutzername = trim((string) $nutzer->getAttribute(self::BENUTZERNAME));

        if ($email === '') {
            return null;
        }

        return new ResetKonto(
            nutzer: $nutzer,
            kontoId: $kontoId,
            email: $email,
            kennung: $benutzername !== '' ? $benutzername : $email,
        );
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

    /**
     * @throws UserModelMisconfiguredException
     */
    private function resetfaehigSicherstellen(): void
    {
        $klasse = $this->userModel->className();

        if (! is_subclass_of($klasse, CanResetPassword::class)) {
            throw new UserModelMisconfiguredException("User model class '{$klasse}' must implement ".CanResetPassword::class.' (e.g. extend Illuminate\Foundation\Auth\User) for password resets.');
        }
    }
}
