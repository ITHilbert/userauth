<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth;

use BackedEnum;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use ITHilbert\UserAuth\App\Console\Commands\AssignRoleCommand;
use ITHilbert\UserAuth\App\Console\Commands\CreatePermissionCommand;
use ITHilbert\UserAuth\App\Console\Commands\PruneSocialLinksCommand;
use ITHilbert\UserAuth\App\Console\Commands\UserAuthCopyFiles;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\AuthSession;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\PendingSocialLinkPruner;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\PendingSocialLinkRepository;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialAccountRepository;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialUserRepository;
use ITHilbert\UserAuth\Application\Zugriff\Contracts\RollenRepository;
use ITHilbert\UserAuth\Application\Zugriff\Zugriffspruefung;
use ITHilbert\UserAuth\Domain\SocialLogin\Events\SocialAccountLinked;
use ITHilbert\UserAuth\Domain\SocialLogin\Events\SocialAccountUnlinked;
use ITHilbert\UserAuth\Http\Middleware\EnforcePasswordPolicy;
use ITHilbert\UserAuth\Http\Middleware\hasPermission;
use ITHilbert\UserAuth\Http\Middleware\hasPermissionAnd;
use ITHilbert\UserAuth\Http\Middleware\hasPermissionOr;
use ITHilbert\UserAuth\Http\Middleware\hasRole;
use ITHilbert\UserAuth\Http\Middleware\isAdmin;
use ITHilbert\UserAuth\Http\Middleware\isDev;
use ITHilbert\UserAuth\Http\Middleware\TwoFactorMiddleware;
use ITHilbert\UserAuth\Http\ViewComposers\AvailableSocialProvidersComposer;
use ITHilbert\UserAuth\Infrastructure\SocialLogin\EloquentPendingSocialLinkRepository;
use ITHilbert\UserAuth\Infrastructure\SocialLogin\EloquentSocialAccountRepository;
use ITHilbert\UserAuth\Infrastructure\SocialLogin\EloquentSocialUserRepository;
use ITHilbert\UserAuth\Infrastructure\SocialLogin\SessionAuth;
use ITHilbert\UserAuth\Infrastructure\SocialLogin\SocialiteIdentityProvider;
use ITHilbert\UserAuth\Infrastructure\Zugriff\EloquentRollenRepository;
use ITHilbert\UserAuth\Listeners\LogAuthenticationAttempt;
use ITHilbert\UserAuth\Listeners\LogSocialAccountChange;
use ITHilbert\UserAuth\Listeners\SendSocialAccountLinkedMail;
use UnitEnum;

class UserAuthServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerTranslations();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->registerRoutes();
        $this->publishAssets();
        $this->registerMiddleware();

        // Commands Registrieren
        $this->commands([
            UserAuthCopyFiles::class,
            AssignRoleCommand::class,
            CreatePermissionCommand::class,
            PruneSocialLinksCommand::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('userauth:prune-social-links')->daily();
        });

        // Register Event Listeners
        Event::listen(
            [Login::class, Failed::class, Logout::class],
            LogAuthenticationAttempt::class
        );

        Event::listen(SocialAccountLinked::class, [SendSocialAccountLinkedMail::class, 'handle']);
        Event::listen([SocialAccountLinked::class, SocialAccountUnlinked::class], LogSocialAccountChange::class);

        View::composer(
            ['userauth::login.*', 'userauth::social.accounts.*'],
            AvailableSocialProvidersComposer::class
        );

        $this->registerGates();
    }

    /**
     * Dev/Admin und Inhaber des gleichnamigen Rechts bestehen jede Gate-Prüfung (`@can`, `Gate::allows`).
     * Sonst null: andere Gates/Policies entscheiden (fail-closed bei ungültiger Rolle).
     */
    protected function registerGates(): void
    {
        Gate::before(function (Authenticatable $user, mixed $ability): ?bool {
            $recht = match (true) {
                $ability instanceof BackedEnum => $ability->value,
                $ability instanceof UnitEnum => $ability->name,
                default => $ability,
            };

            if (! is_string($recht)) {
                return null;
            }

            return $this->app->make(Zugriffspruefung::class)->hatRecht($user, $recht) ? true : null;
        });
    }

    public function registerMiddleware()
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('hasPermissionAnd', hasPermissionAnd::class);
        $router->aliasMiddleware('hasPermissionOr', hasPermissionOr::class);
        $router->aliasMiddleware('hasPermission', hasPermission::class);
        $router->aliasMiddleware('hasRole', hasRole::class);
        $router->aliasMiddleware('isAdmin', isAdmin::class);
        $router->aliasMiddleware('isDev', isDev::class);

        // Füge die EnforcePasswordPolicy Middleware zur globalen "web" Gruppe hinzu
        // (besser: Die Applikation entscheidet selbst, ob sie es nutzen will, oder wir pushen es in den web Group)
        $router->pushMiddlewareToGroup('web', EnforcePasswordPolicy::class);
        $router->pushMiddlewareToGroup('web', TwoFactorMiddleware::class);
    }

    public function publishAssets()
    {
        $this->publishes([
            __DIR__.'/Resources/assets' => public_path('vendor/userauth'),
        ]);
    }

    /**
     * Register Routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/web.php');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        /*  $this->app->register(RouteServiceProvider::class); */
        $this->registerBladeExtensions();
        $this->registerSocialLogin();
        $this->registerZugriffspruefung();
    }

    /**
     * Port-Binding der Zugriffsprüfung; scoped, damit der Rollen-Zwischenspeicher je Request/Job neu beginnt.
     */
    protected function registerZugriffspruefung(): void
    {
        $this->app->scoped(RollenRepository::class, EloquentRollenRepository::class);
    }

    /**
     * Port-Bindings des Social Logins (Ports in Application, Adapter in Infrastructure).
     */
    protected function registerSocialLogin(): void
    {
        $this->app->bind(SocialIdentityProvider::class, SocialiteIdentityProvider::class);
        $this->app->bind(SocialAccountRepository::class, EloquentSocialAccountRepository::class);
        $this->app->bind(PendingSocialLinkRepository::class, EloquentPendingSocialLinkRepository::class);
        $this->app->bind(PendingSocialLinkPruner::class, EloquentPendingSocialLinkRepository::class);
        $this->app->bind(SocialUserRepository::class, EloquentSocialUserRepository::class);
        $this->app->bind(AuthSession::class, SessionAuth::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/Config/config.php' => config_path('userauth.php'),
        ]);

        $this->mergeConfigFrom(
            __DIR__.'/Config/config.php', 'userauth'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $this->publishes([
            __DIR__.'/Resources/views' => resource_path('views/vendor/userauth'),
            __DIR__.'/Resources/views/layouts/userauth.blade.php' => resource_path('views/layouts/userauth.blade.php'),
            __DIR__.'/Resources/views/layouts/auth.blade.php' => resource_path('views/vendor/userauth/layouts/auth.blade.php'),
        ]);

        if (config('userauth.view') == 'ressources') {
            $this->loadViewsFrom(resource_path('views/vendor/userauth'), 'userauth');
        } else {
            $this->loadViewsFrom(__DIR__.'/Resources/views', 'userauth');
        }
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->publishes([
            __DIR__.'/Resources/lang' => resource_path('lang/vendor/userauth'),
        ]);

        if (config('userauth.view') == 'ressources') {
            $this->loadTranslationsFrom(resource_path('/Resources/lang/vendor/userauth'), 'userauth');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/Resources/lang', 'userauth');
        }

        // Globale Auth-Keys (auth.failed, auth.password, auth.throttle) OHNE Namespace
        // registrieren, damit Laravels Standard-Auth-Meldungen projektübergreifend
        // übersetzt werden. App-eigene lang/-Dateien überschreiben dies bei Bedarf.
        $this->loadTranslationsFrom(__DIR__.'/Resources/lang/global', null);
    }

    /**
     * Blade-Direktiven für Rollen und Rechte – alle über die zentrale Zugriffsprüfung.
     * Listen (`@hasPermissionAnd`, `@hasPermissionOr`, `@hasRoleOr`) als Komma-Liste oder Array.
     * `@hasPermission('recht', 'guard')` akzeptiert optional einen Guard.
     */
    protected function registerBladeExtensions(): void
    {
        $this->app->afterResolving('blade.compiler', function (BladeCompiler $bladeCompiler): void {
            $pruefung = '\\'.Zugriffspruefung::class;
            $bedingung = static fn (string $methode, string $argumente, string $guard = ''): string => "app({$pruefung}::class)->{$methode}(auth({$guard})->user(), {$argumente})";
            $mitGuard = static function (string $ausdruck): array {
                [$recht, $guard] = explode(',', $ausdruck.',');

                return [$recht, $guard];
            };

            $bladeCompiler->directive('hasRole', fn (string $rolle): string => '<?php if('.$bedingung('hatRolle', $rolle).'): ?>');
            $bladeCompiler->directive('hasRoleNot', fn (string $rolle): string => '<?php if(! '.$bedingung('hatRolle', $rolle).'): ?>');
            $bladeCompiler->directive('elsehasRole', fn (string $rolle): string => '<?php elseif('.$bedingung('hatRolle', $rolle).'): ?>');
            $bladeCompiler->directive('endhasRole', fn (): string => '<?php endif; ?>');

            $bladeCompiler->directive('hasRoleOr', fn (string $rollen): string => '<?php if('.$bedingung('hatEineDerRollen', $rollen).'): ?>');
            $bladeCompiler->directive('elsehasRoleOr', fn (string $rollen): string => '<?php elseif('.$bedingung('hatEineDerRollen', $rollen).'): ?>');
            $bladeCompiler->directive('endhasRoleOr', fn (): string => '<?php endif; ?>');

            $bladeCompiler->directive('hasPermission', function (string $ausdruck) use ($bedingung, $mitGuard): string {
                [$recht, $guard] = $mitGuard($ausdruck);

                return '<?php if('.$bedingung('hatRecht', $recht, $guard).'): ?>';
            });
            $bladeCompiler->directive('hasPermissionNot', function (string $ausdruck) use ($bedingung, $mitGuard): string {
                [$recht, $guard] = $mitGuard($ausdruck);

                return '<?php if(! '.$bedingung('hatRecht', $recht, $guard).'): ?>';
            });
            $bladeCompiler->directive('elsehasPermission', function (string $ausdruck) use ($bedingung, $mitGuard): string {
                [$recht, $guard] = $mitGuard($ausdruck);

                return '<?php elseif('.$bedingung('hatRecht', $recht, $guard).'): ?>';
            });
            $bladeCompiler->directive('endhasPermission', fn (): string => '<?php endif; ?>');

            $bladeCompiler->directive('hasPermissionOr', fn (string $rechte): string => '<?php if('.$bedingung('hatEinesDerRechte', $rechte).'): ?>');
            $bladeCompiler->directive('elsehasPermissionOr', fn (string $rechte): string => '<?php elseif('.$bedingung('hatEinesDerRechte', $rechte).'): ?>');
            $bladeCompiler->directive('endhasPermissionOr', fn (): string => '<?php endif; ?>');

            $bladeCompiler->directive('hasPermissionAnd', fn (string $rechte): string => '<?php if('.$bedingung('hatAlleRechte', $rechte).'): ?>');
            $bladeCompiler->directive('elsehasPermissionAnd', fn (string $rechte): string => '<?php elseif('.$bedingung('hatAlleRechte', $rechte).'): ?>');
            $bladeCompiler->directive('endhasPermissionAnd', fn (): string => '<?php endif; ?>');
        });
    }
}
