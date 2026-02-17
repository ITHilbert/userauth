<?php

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use ITHilbert\UserAuth\Mail\TwoFactorCode;

class TwoFactorController extends Controller
{
    public function __construct()
    {
        $this->middleware(['web', 'auth']);
    }

    public function index()
    {
        return view('userauth::auth.twoFactor');
    }

    public function store(Request $request)
    {
        $request->validate([
            'two_factor_code' => 'required|string',
        ]);

        $user = auth()->user();

        if ($request->two_factor_code == $user->two_factor_code) {
            $user->resetTwoFactorCode();
            return redirect()->intended(config('userauth.redirect_after_login', '/'));
        }

        return redirect()->back()->withErrors(['two_factor_code' => 'Der eingegebene Code ist ungültig.']);
    }

    public function resend()
    {
        $user = auth()->user();
        $user->generateTwoFactorCode();

        // Mail senden
        Mail::to($user->email)->send(new TwoFactorCode($user->two_factor_code));

        return redirect()->back()->with('message', 'Der Code wurde erneut gesendet.');
    }
}
