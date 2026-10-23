<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Zugriff;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use ITHilbert\UserAuth\Application\Zugriff\Contracts\RollenRepository;
use ITHilbert\UserAuth\Domain\Zugriff\Rolle;
use ITHilbert\UserAuth\Entities\Role;
use WeakMap;

/**
 * Lädt die wirksame Rolle samt Rechten aus `roles`/`role_permission`.
 * Gelöschte Rollen (SoftDeletes) und gelöschte Rechte zählen nicht.
 *
 * Merkt sich das Ergebnis je Nutzer-Objekt, solange sich `role_id` und
 * `current_team_id` nicht ändern (gebunden als scoped → je Request/Job neu).
 */
final class EloquentRollenRepository implements RollenRepository
{
    /** @var WeakMap<Authenticatable, array{schluessel: string, rolle: ?Rolle}> */
    private WeakMap $zwischenspeicher;

    public function __construct()
    {
        $this->zwischenspeicher = new WeakMap;
    }

    public function aktiveRolle(Authenticatable $nutzer): ?Rolle
    {
        $schluessel = $this->schluessel($nutzer);
        $eintrag = $this->zwischenspeicher[$nutzer] ?? null;

        if ($eintrag !== null && $eintrag['schluessel'] === $schluessel) {
            return $eintrag['rolle'];
        }

        $rolle = $this->laden($nutzer);
        $this->zwischenspeicher[$nutzer] = ['schluessel' => $schluessel, 'rolle' => $rolle];

        return $rolle;
    }

    private function laden(Authenticatable $nutzer): ?Rolle
    {
        $id = Rolle::idAus($this->aktiveRollenId($nutzer));

        if ($id === null) {
            return null;
        }

        return Role::query()->with('permissions')->find($id)?->alsZugriffsrolle();
    }

    /**
     * Die Team-Rolle ermittelt der Trait UserAuth (`getActiveRoleId`) – dort bleibt diese Logik
     * an einer Stelle. Fremde Nutzer-Models ohne Trait: nur `role_id`.
     */
    private function aktiveRollenId(Authenticatable $nutzer): mixed
    {
        if (method_exists($nutzer, 'getActiveRoleId')) {
            return $nutzer->getActiveRoleId();
        }

        return $nutzer instanceof Model ? ($nutzer->getAttributes()['role_id'] ?? null) : null;
    }

    private function schluessel(Authenticatable $nutzer): string
    {
        if (! $nutzer instanceof Model) {
            return '';
        }

        // getAttributes(): kein MissingAttributeException bei Model::shouldBeStrict(), wenn eine Spalte fehlt
        $attribute = $nutzer->getAttributes();

        return serialize([$attribute['role_id'] ?? null, $attribute['current_team_id'] ?? null]);
    }
}
