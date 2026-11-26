<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Http\Controllers\AuthCssController;
use ITHilbert\UserAuth\Http\Controllers\BenutzerPasskeysController;
use ITHilbert\UserAuth\Http\Controllers\ImpersonateController;
use ITHilbert\UserAuth\Http\Controllers\LoginController;
use ITHilbert\UserAuth\Http\Controllers\OidcLoginController;
use ITHilbert\UserAuth\Http\Controllers\PasskeyBestaetigungController;
use ITHilbert\UserAuth\Http\Controllers\PasskeyController;
use ITHilbert\UserAuth\Http\Controllers\PasskeyLoginController;
use ITHilbert\UserAuth\Http\Controllers\PasswordController;
use ITHilbert\UserAuth\Http\Controllers\PermissionController;
use ITHilbert\UserAuth\Http\Controllers\RegistrierungController;
use ITHilbert\UserAuth\Http\Controllers\RoleController;
use ITHilbert\UserAuth\Http\Controllers\SocialAccountController;
use ITHilbert\UserAuth\Http\Controllers\SocialLinkConfirmationController;
use ITHilbert\UserAuth\Http\Controllers\SocialLoginController;
use ITHilbert\UserAuth\Http\Controllers\TwoFactorController;
use ITHilbert\UserAuth\Http\Controllers\UserController;
use ITHilbert\UserAuth\Http\Middleware\EnsureOidcEnabled;
use ITHilbert\UserAuth\Http\Middleware\EnsurePasskeyEnabled;
use ITHilbert\UserAuth\Http\Middleware\EnsureRegistrationEnabled;
use ITHilbert\UserAuth\Http\Middleware\EnsureSocialLoginEnabled;
use ITHilbert\UserAuth\Http\Middleware\VerbietetImpersonate;
use ITHilbert\UserAuth\Http\Middleware\VerlangtPasskeyBestaetigung;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the UserAuthServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Asset-Routen ohne Middleware-Gruppe `web`: keine Session, keine Cookies, kein CSRF (statische Antwort, cachebar)
Route::get('userauth/assets/userauth.css', AuthCssController::class)->name('userauth.assets.css');
Route::get('userauth/assets/userauth-verwaltung.css', [AuthCssController::class, 'verwaltung'])->name('userauth.assets.verwaltung');
// Skript der Passkey-Anmeldung, nur mit aktivem Passkey (sonst 404)
Route::get('userauth/assets/passkey.js', [AuthCssController::class, 'passkey'])->name('userauth.assets.passkey')
    ->middleware(EnsurePasskeyEnabled::class);

Route::middleware(['web'])
    ->group(function () {

        // Permission routes
        Route::middleware(config('userauth.routes.permissions_middleware'))
            ->prefix(config('userauth.routes.permissions_prefix'))
            ->group(function () {

                Route::any('/', [PermissionController::class, 'index'])->name('permission');
                Route::get('create', [PermissionController::class, 'create'])->name('permission.create');
                Route::post('store', [PermissionController::class, 'store'])->name('permission.store');
                Route::get('edit/{id}', [PermissionController::class, 'edit'])->name('permission.edit');
                Route::post('update/{id}', [PermissionController::class, 'update'])->name('permission.update');
                Route::delete('delete/{id}', [PermissionController::class, 'delete'])->name('permission.delete');
            });

        // role
        Route::middleware(config('userauth.routes.roles_middleware'))
            ->prefix(config('userauth.routes.roles_prefix'))
            ->group(function () {

                Route::any('/', [RoleController::class, 'index'])->name('role');
                Route::get('create', [RoleController::class, 'create'])->name('role.create')->middleware('hasPermission:role_create');
                Route::post('store', [RoleController::class, 'store'])->name('role.store')->middleware('hasPermission:role_create');
                Route::get('edit/{id}', [RoleController::class, 'edit'])->name('role.edit')->middleware('hasPermission:role_edit');
                Route::post('update/{id}', [RoleController::class, 'update'])->name('role.update')->middleware('hasPermission:role_edit');
                Route::delete('delete/{id}', [RoleController::class, 'delete'])->name('role.delete')->middleware('hasPermission:role_delete');
            });

        // User (Rechte an jeder Route, unabhängig von `users_middleware` einer veröffentlichten Host-Config)
        Route::middleware(config('userauth.routes.users_middleware'))
            ->prefix(config('userauth.routes.users_prefix'))
            ->group(function () {

                Route::any('/', [UserController::class, 'index'])->name('user')->middleware('hasPermission:user_read');
                Route::get('create', [UserController::class, 'create'])->name('user.create')->middleware('hasPermission:user_create');
                Route::post('store', [UserController::class, 'store'])->name('user.store')->middleware('hasPermission:user_create');
                Route::get('edit/{id}', [UserController::class, 'edit'])->name('user.edit')->middleware('hasPermission:user_edit');
                Route::post('update/{id}', [UserController::class, 'update'])->name('user.update')->middleware('hasPermission:user_edit');
                Route::delete('delete/{id}', [UserController::class, 'delete'])->name('user.delete')->middleware('hasPermission:user_delete');
                Route::post('passkeys/reset/{id}', [BenutzerPasskeysController::class, 'zuruecksetzen'])->name('user.passkeys.reset')
                    ->whereNumber('id')->middleware('hasPermission:user_edit');
            });

        // Password edit
        Route::middleware(['auth'])
            ->group(function () {
                Route::get('password/edit', [PasswordController::class, 'edit'])->name('password.edit');
                Route::post('password/update', [PasswordController::class, 'update'])->name('password.update');
            });

        // Logout nur per POST mit CSRF-Token – auch für Gäste mit offener Zwei-Faktor-Anmeldung („Abbrechen“)
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');

        // User Icon
        Route::middleware(['auth'])
            ->group(function () {

                // User Icon
                Route::get('usericon/edit', [UserController::class, 'usericon_edit'])->name('usericon.edit');
                Route::post('usericon/update', [UserController::class, 'usericon_update'])->name('usericon.update');
            });

        // Login
        Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [LoginController::class, 'login']);

        Route::any('no-permission', [PermissionController::class, 'noPermission'])->name('no-permission');

        // Passwort vergessen
        Route::any('password/tokensend', [PasswordController::class, 'tokensend'])->name('password.tokensend');
        Route::any('password/forgotten', [PasswordController::class, 'forgotten'])->name('password.forgotten');
        Route::post('password/sendtocken', [PasswordController::class, 'sendtocken'])->name('password.sendtocken')
            // Mail wird per defer() nach der Antwort verschickt; auch für Hosts mit altem HTTP-Kernel ohne InvokeDeferredCallbacks
            ->middleware(['throttle:userauth-passwort-anfordern', InvokeDeferredCallbacks::class]);
        Route::post('password/updatewithtoken', [PasswordController::class, 'updatewithtoken'])->name('password.updatewithtoken')->middleware('throttle:userauth-passwort-zuruecksetzen');

        // Selbstregistrierung: Routen immer registriert, damit Ein- und Ausschalten ohne neuen Routen-Cache wirkt.
        // EnsureRegistrationEnabled liefert bei userauth.registration.enabled = false zur Laufzeit 404 – vor der
        // CSRF-Prüfung (MiddlewarePrioritaet), also für GET und POST auf jeden Pfad, auch mit Fallback-Route im Host.
        // Mails gehen per defer() nach der Antwort raus; InvokeDeferredCallbacks auch für Hosts mit altem HTTP-Kernel
        Route::middleware([EnsureRegistrationEnabled::class])->prefix('register')->group(function () {
            Route::middleware(['guest'])->group(function () {
                Route::get('/', [RegistrierungController::class, 'create'])->name('register');
                Route::post('/', [RegistrierungController::class, 'store'])->name('register.store')
                    ->middleware(['throttle:userauth-registrieren', InvokeDeferredCallbacks::class]);
                Route::get('bestaetigung', [RegistrierungController::class, 'bestaetigung'])->name('register.bestaetigung');
                Route::post('bestaetigung', [RegistrierungController::class, 'erneut'])->name('register.erneut')
                    ->middleware(['throttle:userauth-registrierung-link', InvokeDeferredCallbacks::class]);
            });
            // Signatur und Ablauf prüft der Controller selbst: ungültige Links bekommen einen Hinweis statt 403
            Route::get('bestaetigen/{id}/{hash}', [RegistrierungController::class, 'bestaetigen'])->name('register.bestaetigen');
            // POST auf den Link: bei „aus“ 404 über die Middleware statt 405 aus einer GET-Fallback-Route des Hosts,
            // bei „an“ 405 wie ohne diese Route
            Route::post('bestaetigen/{id}/{hash}', [RegistrierungController::class, 'nurGet']);
        });

        // 2FA: vor dem Code ist niemand angemeldet; der Controller verlangt eine offene Anmeldung in dieser Sitzung
        Route::middleware(['guest'])->group(function () {
            Route::get('verify', [TwoFactorController::class, 'index'])->name('verify.index');
            Route::post('verify', [TwoFactorController::class, 'store'])->name('verify.store');
            Route::post('verify/resend', [TwoFactorController::class, 'resend'])->name('verify.resend');
        });

        // Impersonate: ändert die Sitzung, daher nur per POST mit CSRF-Token
        Route::middleware(['web', 'auth'])->group(function () {
            Route::post('impersonate/{id}', [ImpersonateController::class, 'impersonate'])->name('userauth.impersonate');
            Route::post('impersonate-leave', [ImpersonateController::class, 'leave'])->name('userauth.impersonate.leave');
        });

        // Anmeldung über den eigenen Anmeldedienst (OIDC): Routen immer registriert, 404 solange
        // userauth.oidc.enabled = false; vor den Social-Routen, damit auth/oidc/… nicht als {provider} greift
        Route::middleware([EnsureOidcEnabled::class, 'throttle:30,1'])->group(function () {
            Route::get('auth/oidc/redirect', [OidcLoginController::class, 'redirect'])->name('userauth.oidc.redirect');
            Route::get('auth/oidc/callback', [OidcLoginController::class, 'callback'])->name('userauth.oidc.callback');
        });

        // Anmeldung mit Passkey: Routen immer registriert, 404 solange userauth.passkey.enabled = false (vor der
        // CSRF-Prüfung, MiddlewarePrioritaet). Anmeldung nur für Gäste und gedrosselt wie OIDC, Verwaltung mit `auth`.
        Route::middleware([EnsurePasskeyEnabled::class])->prefix('passkey')->group(function () {
            Route::middleware(['guest', 'throttle:30,1'])->group(function () {
                Route::post('login/options', [PasskeyLoginController::class, 'optionen'])->name('userauth.passkey.login.options');
                Route::post('login', [PasskeyLoginController::class, 'anmelden'])->name('userauth.passkey.login');
            });

            Route::middleware(['auth'])->group(function () {
                Route::get('/', [PasskeyController::class, 'index'])->name('userauth.passkey.index');
                // Registrieren und Löschen unter Impersonate gesperrt (AK22), Lesen bleibt möglich
                Route::middleware([VerbietetImpersonate::class])->group(function () {
                    // Hinzufügen nur nach Bestätigung per Passwort oder Passkey (passkey-bestaetigung, AK1), Löschen ohne (AK11)
                    Route::middleware([VerlangtPasskeyBestaetigung::class])->group(function () {
                        Route::post('register/options', [PasskeyController::class, 'optionen'])->name('userauth.passkey.register.options');
                        Route::post('register', [PasskeyController::class, 'registrieren'])->name('userauth.passkey.register');
                    });
                    Route::delete('{id}', [PasskeyController::class, 'destroy'])->whereNumber('id')->name('userauth.passkey.destroy');

                    Route::post('bestaetigen', [PasskeyBestaetigungController::class, 'passwort'])->middleware('throttle:5,1,passkey-bestaetigen')->name('userauth.passkey.bestaetigen');
                    Route::post('bestaetigen/optionen', [PasskeyBestaetigungController::class, 'optionen'])->middleware('throttle:30,1,passkey-bestaetigen-passkey')->name('userauth.passkey.bestaetigen.optionen');
                    Route::post('bestaetigen/assertion', [PasskeyBestaetigungController::class, 'assertion'])->middleware('throttle:30,1,passkey-bestaetigen-passkey')->name('userauth.passkey.bestaetigen.assertion');
                });
            });

            // GET auf die Registrierungsrouten: bei „aus“ 404 über die Middleware statt 405, bei „an“ 405
            Route::get('register', [PasskeyController::class, 'nurPost']);
            Route::get('register/options', [PasskeyController::class, 'nurPost']);
        });

        // Social Login (404, solange userauth.social.enabled = false)
        Route::middleware([EnsureSocialLoginEnabled::class, 'throttle:30,1'])->group(function () {
            // Verknüpfen und Lösen unter Impersonate gesperrt (passkey-bestaetigung, AK10); der Callback prüft das selbst,
            // nur wenn ein Benutzer angemeldet ist (Gäste melden sich dort an)
            Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->name('userauth.social.redirect')
                ->middleware(VerbietetImpersonate::class);
            Route::get('auth/{provider}/callback', [SocialLoginController::class, 'callback'])->name('userauth.social.callback');
            Route::get('auth/social/confirm/{token}', [SocialLinkConfirmationController::class, 'show'])->name('userauth.social.confirm');
            Route::post('auth/social/confirm/{token}', [SocialLinkConfirmationController::class, 'store'])->name('userauth.social.confirm.store');

            Route::middleware('auth')->group(function () {
                Route::get('social-accounts', [SocialAccountController::class, 'index'])->name('userauth.social.accounts');
                Route::delete('social-accounts/{provider}', [SocialAccountController::class, 'disconnect'])->name('userauth.social.disconnect')
                    ->middleware(VerbietetImpersonate::class);
            });
        });

        Route::get('password/editwithtoken/{token}/{email}', [PasswordController::class, 'editwithtoken'])->name('password.editwithtoken');
    });
