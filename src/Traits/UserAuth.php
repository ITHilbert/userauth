<?php

namespace ITHilbert\UserAuth\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use ITHilbert\LaravelKit\Traits\VueComboBox;

trait UserAuth
{
    use SoftDeletes;
    use VueComboBox {
        VueComboBox::__construct as private initVueComboBox;
    }

    private $permissions = array();
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
            'image'
        ]);
    }

    public function getKey()
    {
        return $this->id;
    }

    public function getName()
    {
        if (config('userauth.firstname') == true && config('userauth.lastname') == true) {
            return $this->firstname . ' ' . $this->lastname;
        }
        return $this->name;
    }

    /**
     * Teams Relation
     */
    public function teams()
    {
        return $this->belongsToMany(\ITHilbert\UserAuth\Entities\Team::class, 'team_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * Momentan aktives Team
     */
    public function currentTeam()
    {
        return $this->belongsTo(\ITHilbert\UserAuth\Entities\Team::class, 'current_team_id');
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
        return \ITHilbert\UserAuth\Entities\Role::find($this->getActiveRoleId());
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
     * Prüft ob der User eine bestimmte Rolle hat
     *
     * @param string|int|array| $roles
     * @return bool
     */
    public function hasRole($role): bool
    {
        //Ausnahmen für Developer und Admin
        if ($role == 'dev') {
            if ($this->roleName() == 'dev')
                return true;
        } else {
            if ($this->roleName() == 'dev' || $this->roleName() == 'admin')
                return true;
        }

        if ($role == $this->roleName()) {
            return true;
        }

        //kein Treffer
        return false;
    }

    /**
     * Prüft ob der User eine bestimmte Rolle hat
     *
     * @param string|int|array| $roles
     * @return bool
     */
    public function hasRoleOr($roles): bool
    {
        foreach ($roles as $role) {
            if ($role == $this->roleName()) {
                return true;
            }
        }

        //kein Treffer
        return false;
    }

    public function roleDisplayname()
    {
        $activeRole = $this->currentRole();
        return $activeRole ? $activeRole->role_display : '';
    }


    /**
     * Prüft ob die Rolle eine bestimmte Permission hat
     *
     * @param string $permission
     * @return boolean
     */
    public function hasPermission(string $permission)
    {
        //Admin darf immer
        if ($this->getActiveRoleId() <= 2) {
            return true;
        }

        $this->loadPermissions();
        return in_array($permission, $this->permissions);
    }

    /**
     * Prüft ob die Rolle eine bestimmte Permission hat
     *
     * @param string $permission
     * @return boolean
     */
    public function hasPermissionOr($permissions)
    {
        //Admin darf immer
        if ($this->getActiveRoleId() <= 2) {
            return true;
        }

        $this->loadPermissions();

        foreach ($permissions as $perm) {
            if (in_array(trim($perm), $this->permissions)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Prüft ob die Rolle eine bestimmte Permission hat
     *
     * @param string $permission
     * @return boolean
     */
    public function hasPermissionAnd($permissions)
    {
        //Admin darf immer
        if ($this->getActiveRoleId() <= 2) {
            return true;
        }

        $this->loadPermissions();

        foreach ($permissions as $perm) {
            if (!in_array(trim($perm), $this->permissions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lädt die Permissions in die $permissions Variable mittels Eloquent.
     * Ohne Session oder rohe DB-Queries.
     *
     * @param boolean $force true wenn auf jeden Fall neu geladen werden soll
     * @return bool
     */
    private function loadPermissions($force = false)
    {
        $activeRoleId = $this->getActiveRoleId();
        
        //Admin darf immer, rechte müssen nicht geladen werden
        if ($activeRoleId <= 2) {
            return true;
        }

        if ($force || count($this->permissions) == 0) {
            // Mit der aktiven Rolle Permissions laden
            $activeRole = $this->currentRole();
            if ($activeRole && $activeRole->permissions) {
                // Hier müssen wir aufpassen: relation laden auf currentRole statt via $this->role!
                $activeRole->loadMissing('permissions');
                $this->permissions = $activeRole->permissions->pluck('permission')->toArray();
            }
        }

        return true;
    }

    //Helper
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

        if (!in_array($quoteCharacter, ["'", '"'])) {
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
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getImpersonator()
    {
        if ($this->isImpersonated()) {
            $modelClass = config('auth.providers.users.model', '\\App\\Models\\User');
            return $modelClass::find(session()->get('impersonated_by'));
        }
        return null;
    }

    /**
     * Prüft, ob der aktuelle User das Recht hat, als anderer User angemeldet zu werden.
     *
     * @return bool
     */
    public function canImpersonate()
    {
        // Beispiel: Nur Role 1 (Dev) und 2 (Admin) oder eine dedizierte Permission "impersonate_users"
        if ($this->role_id <= 2) {
            return true;
        }

        if (method_exists($this, 'hasPermission') && $this->hasPermission('impersonate_users')) {
            return true;
        }

        return false;
    }

    /**
     * Prüft, ob DIESER User von jemand anderem übernommen werden darf.
     * Admins/Devs dürfen idR nicht von anderen übernommen werden.
     *
     * @return bool
     */
    public function canBeImpersonated()
    {
        // Ein Admin darf sich nicht selbst übernehmen und ein Dev/Admin darf in der Regel nicht von anderen übernommen werden.
        return $this->id !== auth()->id() && $this->role_id > 2;
    }
}


