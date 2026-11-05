<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Benutzer;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use ITHilbert\UserAuth\Application\Benutzer\Contracts\GeloeschteBenutzer;
use ITHilbert\UserAuth\Application\Benutzer\GeloeschterBenutzer;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModelMisconfiguredException;

final class EloquentGeloeschteBenutzer implements GeloeschteBenutzer
{
    private const SOCIAL_KONTEN = 'user_social_accounts';

    private const OFFENE_VERKNUEPFUNGEN = 'user_pending_social_links';

    private const TEAM_MITGLIEDSCHAFTEN = 'team_user';

    private const PASSWORT_HISTORIE = 'user_password_histories';

    public function __construct(
        private readonly UserModel $userModel,
        private readonly ConnectionInterface $db,
        private readonly Config $config,
    ) {}

    public function existiert(string $email): bool
    {
        return $this->geloeschteMitEmail($email)->exists();
    }

    public function mitEmail(string $email): array
    {
        return $this->geloeschteMitEmail($email)
            ->reorder()
            ->orderByDesc($this->deletedAtSpalte())
            ->orderByDesc($this->userModel->newInstance()->getKeyName())
            ->lockForUpdate()
            ->get()
            ->map(static fn (Model $nutzer): GeloeschterBenutzer => new GeloeschterBenutzer(
                $nutzer->getKey(),
                (string) $nutzer->getAttribute('email'),
                $nutzer->getAttribute('image') === null ? null : (string) $nutzer->getAttribute('image'),
            ))
            ->values()
            ->all();
    }

    public function wiederherstellen(GeloeschterBenutzer $benutzer): void
    {
        $this->verknuepfungenEntfernen($benutzer, mitHistorie: false);

        $nutzer = $this->geloeschteZeile($benutzer);
        $nutzer->forceFill([
            'current_team_id' => null,
            'remember_token' => Str::random(60),
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        if (! method_exists($nutzer, 'restore') || $nutzer->restore() !== true) {
            throw new GeloeschterBenutzerUnveraendertException("User {$benutzer->id} could not be restored.");
        }
    }

    public function endgueltigLoeschen(GeloeschterBenutzer $benutzer): void
    {
        $this->verknuepfungenEntfernen($benutzer, mitHistorie: true);

        $nutzer = $this->geloeschteZeile($benutzer);

        // Das Model nutzt SoftDeletes (geprüft in geloeschte()): forceDelete entfernt die Zeile wirklich.
        if ($nutzer->forceDelete() !== true) {
            throw new GeloeschterBenutzerUnveraendertException("User {$benutzer->id} could not be purged.");
        }
    }

    /**
     * Ausdrücklich gelöscht, nicht über Fremdschlüssel: Altbestände haben nicht überall `cascade`.
     */
    private function verknuepfungenEntfernen(GeloeschterBenutzer $benutzer, bool $mitHistorie): void
    {
        $tabellen = [self::SOCIAL_KONTEN, self::OFFENE_VERKNUEPFUNGEN, self::TEAM_MITGLIEDSCHAFTEN];
        if ($mitHistorie) {
            $tabellen[] = self::PASSWORT_HISTORIE;
        }

        foreach ($tabellen as $tabelle) {
            $this->db->table($tabelle)->where('user_id', $benutzer->id)->delete();
        }

        $this->db->table($this->resetTokenTabelle())
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($benutzer->email)])
            ->delete();
    }

    private function geloeschteZeile(GeloeschterBenutzer $benutzer): Model
    {
        return $this->geloeschte()->whereKey($benutzer->id)->firstOrFail();
    }

    /**
     * @return Builder<Model>
     */
    private function geloeschteMitEmail(string $email): Builder
    {
        return $this->geloeschte()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
    }

    /**
     * Nur weich gelöschte Zeilen; andere globale Scopes des Hosts bleiben aktiv.
     *
     * @return Builder<Model>
     *
     * @throws UserModelMisconfiguredException User-Model ohne SoftDeletes
     */
    private function geloeschte(): Builder
    {
        return $this->userModel->query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereNotNull($this->deletedAtSpalte());
    }

    /**
     * @throws UserModelMisconfiguredException
     */
    private function deletedAtSpalte(): string
    {
        $nutzer = $this->userModel->newInstance();

        if (! in_array(SoftDeletes::class, class_uses_recursive($nutzer), true) || ! method_exists($nutzer, 'getQualifiedDeletedAtColumn')) {
            throw new UserModelMisconfiguredException('User model '.$nutzer::class.' must use SoftDeletes (trait ITHilbert\UserAuth\Traits\UserAuth).');
        }

        return (string) $nutzer->getQualifiedDeletedAtColumn();
    }

    private function resetTokenTabelle(): string
    {
        $broker = (string) $this->config->get('auth.defaults.passwords', 'users');

        return (string) $this->config->get("auth.passwords.{$broker}.table", 'password_reset_tokens');
    }
}
