<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Entities;

use Illuminate\Database\Eloquent\Model;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;

class Team extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function users()
    {
        return $this->belongsToMany(app(UserModel::class)->className(), 'team_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }
}
