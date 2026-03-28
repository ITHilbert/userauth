<?php

namespace ITHilbert\UserAuth;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Compilers\BladeCompiler;

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
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->registerRoutes();
        $this->publishAssets();
        $this->registerMiddleware();

        //Commands Registrieren
        $this->commands([
            \ITHilbert\UserAuth\App\Console\Commands\UserAuthCopyFiles::class,
            \ITHilbert\UserAuth\App\Console\Commands\AssignRoleCommand::class,
            \ITHilbert\UserAuth\App\Console\Commands\CreatePermissionCommand::class,
        ]);

        // Register Event Listeners
        \Illuminate\Support\Facades\Event::listen(
            [\Illuminate\Auth\Events\Login::class, \Illuminate\Auth\Events\Failed::class, \Illuminate\Auth\Events\Logout::class],
            \ITHilbert\UserAuth\Listeners\LogAuthenticationAttempt::class
        );

        $this->registerGates();
    }

    protected function registerGates()
    {
        Gate::before(function ($user, $ability) {
            // Admin/Dev haben alle Rechte (role_id 1 = Dev, 2 = Admin)
            if ($user->role_id <= 2) {
                return true;
            }

            // Prüfe, ob das Package diese Permission kennt und freigibt
            if (method_exists($user, 'hasPermission') && $user->hasPermission($ability)) {
                return true;
            }

            // Wenn null zurückgegeben wird, laufen andere Gates normal weiter
            return null;
        });
    }



    public function registerMiddleware()
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('hasPermissionAnd', \ITHilbert\UserAuth\Http\Middleware\hasPermissionAnd::class);
        $router->aliasMiddleware('hasPermissionOr', \ITHilbert\UserAuth\Http\Middleware\hasPermissionOr::class);
        $router->aliasMiddleware('hasPermission', \ITHilbert\UserAuth\Http\Middleware\hasPermission::class);
        $router->aliasMiddleware('hasRole', \ITHilbert\UserAuth\Http\Middleware\hasRole::class);
        $router->aliasMiddleware('isAdmin', \ITHilbert\UserAuth\Http\Middleware\isAdmin::class);
        $router->aliasMiddleware('isDev', \ITHilbert\UserAuth\Http\Middleware\isDev::class);

        // Füge die EnforcePasswordPolicy Middleware zur globalen "web" Gruppe hinzu 
        // (besser: Die Applikation entscheidet selbst, ob sie es nutzen will, oder wir pushen es in den web Group)
        $router->pushMiddlewareToGroup('web', \ITHilbert\UserAuth\Http\Middleware\EnforcePasswordPolicy::class);
        $router->pushMiddlewareToGroup('web', \ITHilbert\UserAuth\Http\Middleware\TwoFactorMiddleware::class);
    }


    public function publishAssets()
    {
        $this->publishes([
            __DIR__ . '/Resources/assets' => public_path('vendor/userauth'),
        ]);
    }

    /**
     * Register Routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
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
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__ . '/Config/config.php' => config_path('userauth.php'),
        ]);
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $this->publishes([
            __DIR__ . '/Resources/views' => resource_path('views/vendor/userauth'),
            __DIR__ . '/Resources/views/layouts/userauth.blade.php' => resource_path('views/layouts/userauth.blade.php'),
        ]);

        if (config('userauth.view') == 'ressources') {
            $this->loadViewsFrom(resource_path('Resources/views/vendor/userauth'), 'userauth');
        } else {
            $this->loadViewsFrom(__DIR__ . '/Resources/views', 'userauth');
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
            __DIR__ . '/Resources/lang' => resource_path('lang/vendor/userauth'),
        ]);

        if (config('userauth.view') == 'ressources') {
            $this->loadTranslationsFrom(resource_path('/Resources/lang/vendor/userauth'), 'userauth');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/Resources/lang', 'userauth');
        }
    }


    /**
     * Eigende Blade function (Directive)
     *
     * @return void
     */
    protected function registerBladeExtensions()
    {
        $this->app->afterResolving('blade.compiler', function (BladeCompiler $bladeCompiler) {

            /* hasRole */
            $bladeCompiler->directive('hasRole', function ($role, $guard = '') {
                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasRole({$role})): ?>";
            });
            /* hasRoleNot */
            $bladeCompiler->directive('hasRoleNot', function ($role, $guard = '') {
                return "<?php if(auth({$guard})->check() && !auth({$guard})->user()->hasRole({$role})): ?>";
            });
            /* elsehasRole */
            $bladeCompiler->directive('elsehasRole', function ($role, $guard = '') {
                return "<?php elseif(auth({$guard})->check() && auth({$guard})->user()->hasRole({$role})): ?>";
            });
            /* endhasRole */
            $bladeCompiler->directive('endhasRole', function () {
                return '<?php endif; ?>';
            });

            /* hasRoleOr */
            $bladeCompiler->directive('hasRoleOr', function ($roles, $guard = '') {
                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasRoleOr({$roles})): ?>";
            });
            /* elsehasRole */
            $bladeCompiler->directive('elsehasRoleOr', function ($roles, $guard = '') {
                return "<?php elseif(auth({$guard})->check() && auth({$guard})->user()->hasRoleOr({$roles})): ?>";
            });
            /* endhasRoleOr */
            $bladeCompiler->directive('endhasRoleOr', function () {
                return '<?php endif; ?>';
            });

            /* ##################################################### */
            /* hasPermission */
            $bladeCompiler->directive('hasPermission', function ($arguments, $guard = '') {
                list($permission, $guard) = explode(',', $arguments . ',');
                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasPermission({$permission})): ?>";
            });
            /* hasPermissionNot */
            $bladeCompiler->directive('hasPermissionNot', function ($arguments, $guard = '') {
                list($permission, $guard) = explode(',', $arguments . ',');
                return "<?php if(auth({$guard})->check() && !auth({$guard})->user()->hasPermission({$permission})): ?>";
            });
            /* elsehasPermission */
            $bladeCompiler->directive('elsehasPermission', function ($arguments, $guard = '') {
                list($permission, $guard) = explode(',', $arguments . ',');
                return "<?php elseif(auth({$guard})->check() && auth({$guard})->user()->hasPermission({$permission})): ?>";
            });

            /* hasPermissionOr */
            $bladeCompiler->directive('hasPermissionOr', function ($permissions, $guard = '') {
                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasPermissionOr({$permissions})): ?>";
            });

            /* elsehasPermissionOr */
            $bladeCompiler->directive('elsehasPermissionOr', function ($permissions, $guard = '') {
                return "<?php elseif(auth({$guard})->check() && auth({$guard})->user()->hasPermissionOr({$permissions})): ?>";
            });

            /* hasPermissionAnd */
            $bladeCompiler->directive('hasPermissionAnd', function ($permissions, $guard = '') {
                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasPermissionAnd({$permissions})): ?>";
            });

            /* elsehasPermissionAnd */
            $bladeCompiler->directive('elsehasPermissionAnd', function ($permissions, $guard = '') {
                return "<?php elseif(auth({$guard})->check() && auth({$guard})->user()->hasPermissionAnd({$permissions})): ?>";
            });

            /* endhasPermission */
            $bladeCompiler->directive('endhasPermission', function () {
                return '<?php endif; ?>';
            });

            /* endhasPermissionOr */
            $bladeCompiler->directive('endhasPermissionOr', function () {
                return '<?php endif; ?>';
            });

            /* endhasPermissionAnd */
            $bladeCompiler->directive('endhasPermissionAnd', function () {
                return '<?php endif; ?>';
            });


        });
    }
}
