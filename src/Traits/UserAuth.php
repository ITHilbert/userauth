<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use ITHilbert\LaravelKit\Traits\VueComboBox;
use ITHilbert\UserAuth\Application\Zugriff\Zugriffspruefung;
use ITHilbert\UserAuth\Entities\Role;
use ITHilbert\UserAuth\Entities\Team;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;

trait UserAuth
{
    use SoftDeletes;
    use VueComboBox;

    /** Nie serialisieren (toArray/toJson): Zugangsdaten, 2FA-Code und Reset-Token. */
    private const USERAUTH_HIDDEN = [
        'password',
        'remember_token',
        'two_factor_code',
        'two_factor_expires_at',
        'edit_pw_token',
        'edit_pw_token_end',
    ];

    private $role_name = '';

    public function getCbCaptionAttribute()
    {
        return $this->name;
    }

    public function initializeUserAuth()
    {
        // Initialsierung nur, wenn das Paket aktiv benötigt wird. VueComboBox ruft sich selbst initial auf (initializeVueComboBox o.ä. in aktuelleren Versionen),
        // daher belassen wir nur die Attribute, die wir fillable machen wollen.

        $this->fillable = array_merge($this->fillable, [
            'role_id',
            'anrede_id',
            'title',
            'firstname',
            'lastname',
            'smallname',
            'street',
            'postcode',
            'city',
            'country',
            'signature_rule_id',
            'ustid',
            'phone',
            'phone2',
            'mobile',
            'fax',
            'private_email',
            'skype',
            'hourly_rate',
            'birthday',
            'comment',
            'image',
        ]);

        // Ergänzt ein eigenes $hidden der Model-Klasse, ersetzt es nicht
        $this->mergeHidden(self::USERAUTH_HIDDEN);
    }

    public function getKey()
    {
        return $this->id;
    }

    public function getName()
    {
        if (config('userauth.firstname') == true && config('userauth.lastname') == true) {
            return $this->firstname.' '.$this->lastname;
        }

        return $this->name;
    }

    /**
     * Teams Relation
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * Momentan aktives Team
     */
    public function currentTeam()
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Ermittelt die korrekte Rolle (Mandantenfähig)
     */
    public function getActiveRoleId()
    {
        if (config('userauth.teams.enabled', false) && $this->current_team_id) {
            // Hole das Pivot role_id für das aktive Team
            $teamUser = DB::table('team_user')
                ->where('user_id', $this->id)
                ->where('team_id', $this->current_team_id)
                ->first();

            if ($teamUser && $teamUser->role_id) {
                return $teamUser->role_id;
            }
        }

        return $this->role_id;
    }

    /**
     * Aktives Rollen-Objekt holen
     */
    public function currentRole()
    {
        $rollenId = $this->getActiveRoleId();

        // Vorab geladene Relation `role` nutzen (z. B. `with('role')` in Listen), solange sie zur aktiven Rolle passt
        $geladen = $this->relationLoaded('role') ? $this->getRelation('role') : null;
        if ($geladen instanceof Role && $rollenId !== null && (string) $geladen->getKey() === (string) $rollenId) {
            return $geladen;
        }

        return Role::find($rollenId);
    }

    public function getImagePath()
    {
        if ($this->image !== '') {
            return asset($this->image);
        }

        return asset('vendor/userauth/img/default-user.jpg');
    }

    public function role()
    {
        return $this->hasOne('ITHilbert\UserAuth\Entities\Role', 'id', 'role_id');
    }

    public function roleName()
    {
        if ($this->role_name == '') {
            $activeRole = $this->currentRole();
            if ($activeRole) {
                $this->role_name = $activeRole->role;
            }
        }

        return $this->role_name;
    }

    /**
     * Prüft die Rolle (Dev erfüllt jede, Admin jede außer `dev`, sonst der eigene Rollenname).
     * Kein Text (z. B. Array) → nur Dev/Admin.
     */
    public function hasRole(mixed $role): bool
    {
        if (! is_string($role) && ! is_int($role)) {
            return $this->userAuthZugriffspruefung()->istPrivilegiert($this);
        }

        return $this->userAuthZugriffspruefung()->hatRolle($this, (string) $role);
    }

    /**
     * Prüft, ob der User eine der Rollen trägt (ohne Sonderstellung für Dev/Admin).
     *
     * @param  string|array<array-key, mixed>  $roles  Komma-Liste oder Array
     */
    public function hasRoleOr(string|array $roles): bool
    {
        return $this->userAuthZugriffspruefung()->hatEineDerRollen($this, $roles);
    }

    public function roleDisplayname()
    {
        $activeRole = $this->currentRole();

        return $activeRole ? $activeRole->role_display : '';
    }

    /**
     * Prüft, ob der User das Recht hat (Dev/Admin: immer).
     */
    public function hasPermission(string $permission): bool
    {
        return $this->userAuthZugriffspruefung()->hatRecht($this, $permission);
    }

    /**
     * Prüft, ob der User MINDESTENS EINES der Rechte hat (ODER).
     *
     * @param  string|array<array-key, mixed>  $permissions  Komma-Liste oder Array
     */
    public function hasPermissionOr(string|array $permissions): bool
    {
        return $this->userAuthZugriffspruefung()->hatEinesDerRechte($this, $permissions);
    }

    /**
     * Prüft, ob der User ALLE Rechte hat (UND).
     *
     * @param  string|array<array-key, mixed>  $permissions  Komma-Liste oder Array
     */
    public function hasPermissionAnd(string|array $permissions): bool
    {
        return $this->userAuthZugriffspruefung()->hatAlleRechte($this, $permissions);
    }

    /**
     * Alle Rollen- und Rechteprüfungen laufen über die zentrale Zugriffsprüfung.
     */
    private function userAuthZugriffspruefung(): Zugriffspruefung
    {
        return app(Zugriffspruefung::class);
    }

    // Helper
    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (! in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }

    public function generateTwoFactorCode()
    {
        $this->two_factor_code = rand(100000, 999999);
        $this->two_factor_expires_at = now()->addMinutes(10);
        $this->save();
    }

    public function resetTwoFactorCode()
    {
        $this->two_factor_code = null;
        $this->two_factor_expires_at = null;
        $this->save();
    }

    /**
     * Prüft, ob der aktuelle User von einem Admin (Support) im "Impersonate"-Modus betrieben wird.
     *
     * @return bool
     */
    public function isImpersonated()
    {
        return session()->has('impersonated_by');
    }

    /**
     * Gibt den Admin (Impersonator) zurück, der gerade in diesem Account eingeloggt ist.
     *
     * @return Model|null
     */
    public function getImpersonator()
    {
        if ($this->isImpersonated()) {
            return app(UserModel::class)->query()->find(session()->get('impersonated_by'));
        }

        return null;
    }

    /**
     * Prüft, ob der aktuelle User andere Nutzer übernehmen darf (Dev/Admin oder Recht `impersonate_users`).
     */
    public function canImpersonate(): bool
    {
        return $this->userAuthZugriffspruefung()->hatRecht($this, 'impersonate_users');
    }

    /**
     * Prüft, ob DIESER User von jemand anderem übernommen werden darf:
     * nicht man selbst, nur mit gültiger Rolle und nie Dev/Admin.
     */
    public function canBeImpersonated(): bool
    {
        $rolle = $this->userAuthZugriffspruefung()->aktiveRolle($this);

        return $this->id !== auth()->id()
            && $rolle !== null
            && ! $rolle->istPrivilegiert();
    }
}
