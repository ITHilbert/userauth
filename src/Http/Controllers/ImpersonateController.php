<?php

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    /**
     * Start impersonating another user.
     *
     * @param int $id The ID of the user to impersonate
     * @return \Illuminate\Http\RedirectResponse
     */
    public function impersonate($id)
    {
        if (!config('userauth.impersonate_enabled', true)) {
            abort(403, 'Impersonation is disabled in configuration.');
        }

        $userModelClass = config('auth.providers.users.model', '\\App\\Models\\User');
        $targetUser = $userModelClass::findOrFail($id);
        $currentUser = Auth::user();

        // Check if current user is allowed to impersonate
        if (method_exists($currentUser, 'canImpersonate') && !$currentUser->canImpersonate()) {
            abort(403, 'You do not have permission to impersonate.');
        }

        // Check if target user is allowed to be impersonated
        if (method_exists($targetUser, 'canBeImpersonated') && !$targetUser->canBeImpersonated()) {
            abort(403, 'This user cannot be impersonated.');
        }

        // Save original admin ID to session
        session()->put('impersonated_by', $currentUser->id);
        
        // Login as the target user without password
        Auth::login($targetUser);

        // Redirect appropriately
        $redirect = config('userauth.redirect_after_login', '/home');
        return redirect($redirect)->with('success', 'You are now impersonating ' . $targetUser->getName());
    }

    /**
     * Stop impersonating and return to the original user account.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function leave()
    {
        if (!session()->has('impersonated_by')) {
            return redirect()->back();
        }

        $impersonatorId = session()->get('impersonated_by');
        session()->forget('impersonated_by');

        $userModelClass = config('auth.providers.users.model', '\\App\\Models\\User');
        $impersonator = $userModelClass::findOrFail($impersonatorId);

        // Login back as the admin
        Auth::login($impersonator);

        $redirect = config('userauth.redirect_after_login', '/home');
        return redirect($redirect)->with('success', 'You have returned to your original account.');
    }
}
