<?php

namespace ITHilbert\UserAuth\Entities;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function users()
    {
        return $this->belongsToMany(config('auth.providers.users.model', '\\App\\Models\\User'), 'team_user')
                    ->withPivot('role_id')
                    ->withTimestamps();
    }
}
