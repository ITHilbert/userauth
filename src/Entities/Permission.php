<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ITHilbert\LaravelKit\Traits\VueComboBox;

class Permission extends Model
{
    use SoftDeletes;
    use VueComboBox;

    protected $table = 'permissions';

    protected $fillable = [
        'permission',
        'permission_display',
        'group_id',
        'crud',
    ];

    public function getCbCaptionAttribute()
    {
        return $this->permission_display;
    }

    public function getGroupName()
    {
        $group = PermissionGroup::find($this->group_id);

        return $group->group_display;
    }
}
