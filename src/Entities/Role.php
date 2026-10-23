<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ITHilbert\LaravelKit\Traits\VueComboBox;
use ITHilbert\UserAuth\Domain\Zugriff\Namensliste;
use ITHilbert\UserAuth\Domain\Zugriff\Rolle;

class Role extends Model
{
    use SoftDeletes;
    use VueComboBox;

    protected $table = 'roles';

    protected $fillable = [
        'role',
        'role_display',
    ];

    public function getCbCaptionAttribute()
    {
        return $this->role_display;
    }

    public function permissions()
    {
        return $this->belongsToMany('ITHilbert\UserAuth\Entities\Permission', 'role_permission', 'role_id', 'permission_id');
    }

    /**
     * Diese Rolle als Domain-Objekt der Zugriffsprüfung; null bei nicht gespeicherter,
     * gelöschter oder ungültiger Rolle (fail-closed).
     */
    public function alsZugriffsrolle(): ?Rolle
    {
        $id = Rolle::idAus($this->getKey());

        if ($id === null || ! $this->exists || $this->trashed()) {
            return null;
        }

        $this->loadMissing('permissions');

        /** @var list<string> $rechte */
        $rechte = $this->getRelation('permissions')
            ->pluck('permission')
            ->map(static fn (mixed $recht): string => (string) $recht)
            ->values()
            ->all();

        return new Rolle($id, (string) $this->role, $rechte);
    }

    /**
     * Prüft, ob die Rolle das Recht hat (Dev/Admin: immer).
     */
    public function hasPermission(string $permission): bool
    {
        return $this->alsZugriffsrolle()?->hatRecht($permission) ?? false;
    }

    /**
     * Prüft ob die Rolle eine bestimmte Permission hat
     *
     * @param  string  $permission
     * @return bool
     */
    public function hasPermissionInView($permission)
    {
        $permission = $this->permissions()->where('permission', $permission)->first();
        if ($permission) {
            return true;
        }

        return false;
    }

    /**
     * Prüft, ob die Rolle MINDESTENS EINES der Rechte hat (ODER).
     *
     * @param  string|array<array-key, mixed>  $permissions  Komma-Liste oder Array
     */
    public function hasPermissionOr(string|array $permissions): bool
    {
        return $this->alsZugriffsrolle()?->hatEinesDerRechte(Namensliste::aus($permissions)) ?? false;
    }

    /**
     * Prüft, ob die Rolle ALLE Rechte hat (UND).
     *
     * @param  string|array<array-key, mixed>  $permissions  Komma-Liste oder Array
     */
    public function hasPermissionAnd(string|array $permissions): bool
    {
        return $this->alsZugriffsrolle()?->hatAlleRechte(Namensliste::aus($permissions)) ?? false;
    }
}
