<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use ITHilbert\UserAuth\Entities\Role;

final class TestController extends Controller
{
    public function role()
    {
        $role = Role::find(1);

        return view('userauth::test.role')->with(compact('role'));
    }

    public function user()
    {
        $user = User::find(1);

        return view('userauth::test.user')->with(compact('user'));
    }
}
