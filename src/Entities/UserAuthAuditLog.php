<?php

namespace ITHilbert\UserAuth\Entities;

use Illuminate\Database\Eloquent\Model;

class UserAuthAuditLog extends Model
{
    protected $table = 'user_auth_audit_logs';

    protected $fillable = [
        'user_id',
        'email',
        'event',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
