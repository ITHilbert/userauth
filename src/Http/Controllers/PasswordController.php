<?php

namespace ITHilbert\UserAuth\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use ITHilbert\LaravelKit\Helpers\MyDateTime;
use ITHilbert\UserAuth\App\Mail\ForgottenPassword;
use Illuminate\Support\Str;
use ITHilbert\LaravelKit\Helpers\Breadcrumb;
use ITHilbert\UserAuth\Rules\PasswordHistoryRule;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PasswordController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('throttle:5,1', only: ['sendtocken']),
        ];
    }

    /**
     * Edit the form for editing the specified resource.
     *
     * @param  \App\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function edit()
    {
        $breadcrumb = new Breadcrumb();
        $breadcrumb->add(trans('userauth::password.header_change'));

        return view('userauth::password.edit')->with(compact('breadcrumb'));
    }


    /**
     * Passwort Änderung speichern
     *
     * @param  \Illuminate\Http\Request  $requestliste
     * @param  \App\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $user = Auth()->user();

        $request->validate([
            'password' => ['required', 'min:8', 'confirmed', new PasswordHistoryRule($user)],
            'password_confirmation' => 'required|same:password'
        ]);

        $this->savePasswordHistory($user);

        $user->password = Hash::make($request->password);
        $user->password_changed_at = now();
        $user->update();

        $redirectPath = config('userauth.redirect_after_login', '/');

        return redirect($redirectPath)
            ->with('message', 'Passwort wurde geändert');
    }



    /**
     * Öffnet das Formular um den Passwort ändern Token anzufordern
     *
     * @return void
     */
    public function forgotten()
    {
        return view('userauth::password.forgotten');
    }

    /**
     * Sendet die Mail mit dem Änderungstoken
     *
     * @param Request $request
     * @return void
     */
    public function sendtocken(Request $request)
    {

        $request->validate([
            'email' => 'required',
        ]);

        $email = $request->email;

        $user = User::where('email', $email)->where('deleted_at', NULL)->first();

        $user->edit_pw_token = Str::random(10);

        $zeit = new MyDateTime('now');
        $zeit->addMin(30);
        $user->edit_pw_token_end = $zeit->getDateTimeISO();

        $user->update();

        if (isset($user)) {
            Mail::to($email)->send(new ForgottenPassword($user));

            return redirect()->route('password.tokensend')
                ->with('message', 'Bitte prüfen Sie Ihren Posteingang (ggf. auch den SPAM Ordner).');
        } else {
            redirect()->back()->withErrors('Kein Treffer');
        }
    }

    /**
     * Öffnet die View Token wurde gesendet
     *
     * @return void
     */
    public function tokensend()
    {
        return view('userauth::password.tokensend');
    }


    /**
     * Öffnet die View zum Passwort ändern mit Token
     *
     * @param [type] $token der Token zum ändern des Passwortes
     * @param [type] $email die E-Mail Adresse des Users
     * @return void
     */
    public function editwithtoken($token, $email)
    {
        return view('userauth::password.editwithtoken')->with(compact('token', 'email'));
    }

    /**
     * Ändert das Passwort mit Token
     *
     * @param Request $request
     * @return void
     */
    public function updatewithtoken(Request $request)
    {
        $user = User::where('email', $request->email)->where('edit_pw_token', $request->pwtoken)->where('deleted_at', NULL)->first();

        $request->validate([
            'password' => ['required', 'min:8', 'confirmed', new PasswordHistoryRule($user)],
            'password_confirmation' => 'required|same:password',
            'pwtoken' => 'required',
            'email' => 'email',
        ]);

        $date1 = new MyDateTime('now');
        $date2 = new MyDateTime($user->edit_pw_token_end);

        //echo $date1->getTimestamp() . ' - ' . $date2->getTimestamp();

        if ($date1->getTimestamp() > $date2->getTimestamp()) {
            return redirect()->route('password.forgotten')->withErrors('Token ist abgelaufen.');
        }

        if (isset($user)) {
            $this->savePasswordHistory($user);

            $user->password = Hash::make($request->password);
            $user->password_changed_at = now();
            $user->edit_pw_token = NULL;
            $user->edit_pw_token_end = null;
            $user->update();

            $redirectPath = config('userauth.redirect_after_login', '/');

            return redirect($redirectPath)
                ->with('message', 'Passwort wurde geändert');
        } else {
            return redirect()->route('password.forgotten')->withErrors('Änderung nicht möglich.');
        }
    }

    private function savePasswordHistory($user)
    {
        if (config('userauth.password_policy.enabled', false)) {
            DB::table('user_password_histories')->insert([
                'user_id' => $user->id,
                'password' => $user->password, // Altes Passwort
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
