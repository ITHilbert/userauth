<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use ITHilbert\UserAuth\Application\SocialLogin\ConfirmPendingSocialLink;
use ITHilbert\UserAuth\Domain\SocialLogin\Exceptions\SocialLoginException;

final class SocialLinkConfirmationController extends Controller
{
    /**
     * Zeigt nur die Bestätigungsseite (GET verknüpft nie: Schutz vor Mail-Scannern).
     */
    public function show(string $token, ConfirmPendingSocialLink $confirm): View|RedirectResponse
    {
        try {
            $pending = $confirm->preview($token);
        } catch (SocialLoginException $e) {
            return redirect()->route('login')->withErrors(['social' => $e->getMessage()]);
        }

        return view('userauth::social.confirm', ['token' => $token, 'provider' => $pending->provider]);
    }

    public function store(string $token, ConfirmPendingSocialLink $confirm): RedirectResponse
    {
        try {
            $confirm->handle($token);
        } catch (SocialLoginException $e) {
            return redirect()->route('login')->withErrors(['social' => $e->getMessage()]);
        }

        return redirect()->route('login')->with('status', __('userauth::social.status.link_confirmed'));
    }
}
