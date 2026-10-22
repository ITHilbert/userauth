<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ITHilbert\UserAuth\Application\SocialLogin\DisconnectSocialAccount;
use ITHilbert\UserAuth\Application\SocialLogin\ListSocialAccounts;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialProvider;

final class SocialAccountController extends Controller
{
    public function index(Request $request, ListSocialAccounts $accounts): View
    {
        return view('userauth::social.accounts', [
            'providers' => SocialProvider::cases(),
            'linked' => $accounts->handle($request->user()->getAuthIdentifier()),
        ]);
    }

    public function disconnect(Request $request, SocialProvider $provider, DisconnectSocialAccount $disconnect): RedirectResponse
    {
        $user = $request->user();

        $disconnect->handle($user->getAuthIdentifier(), (string) $user->email, $provider);

        return redirect()->route('userauth.social.accounts')
            ->with('status', __('userauth::social.status.disconnected', ['provider' => $provider->label()]));
    }
}
