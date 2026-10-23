<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use ITHilbert\UserAuth\Traits\UserAuth;

/**
 * Test-User des Pakets – ersetzt das Host-Model App\Models\User.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;
    use UserAuth;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
