<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * @return BelongsTo<PermissionGroup, $this>
     */
    public function gruppe(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'group_id');
    }

    /**
     * Rechte, die in den Rollenformularen zuweisbar sind: alle außer denen einer vorhandenen
     * Gruppe, die `PermissionGroup::fuerRollenformular()` ausblendet. Welche Gruppen das sind,
     * steht nur dort; Gruppen ohne Treffer im Scope (auch gelöschte) gelten als verborgen.
     *
     * @param  Builder<Permission>  $query
     */
    public function scopeFuerRollenformular(Builder $query): void
    {
        $query->whereDoesntHave('gruppe', static function (Builder $gruppen): void {
            /** @var Builder<PermissionGroup> $gruppen */
            $gruppen->withoutGlobalScopes()->whereNotIn(
                $gruppen->qualifyColumn('id'),
                PermissionGroup::query()->fuerRollenformular()->select('id')
            );
        });
    }

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
