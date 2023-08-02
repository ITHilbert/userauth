<?php

namespace ITHilbert\UserAuth;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
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
        $this->publishMenuFilters();
        $this->registerMiddleware();

        //Commands Registrieren
        $this->commands( \ITHilbert\UserAuth\App\Console\Commands\UserAuthCopyFiles::class );
    }

    public function publishMenuFilters(){
        $this->publishes([
            __DIR__ .'/App/Menu/Filters/' => app_path('Menu/Filters'),
        ]);
    }


    public function registerMiddleware(){
        $this->app['router']->aliasMiddleware('hasPermissionAnd' , \ITHilbert\UserAuth\Http\Middleware\hasPermissionAnd::class);
        $this->app['router']->aliasMiddleware('hasPermissionOr' , \ITHilbert\UserAuth\Http\Middleware\hasPermissionOr::class);
        $this->app['router']->aliasMiddleware('hasPermission', \ITHilbert\UserAuth\Http\Middleware\hasPermission::class);
        $this->app['router']->aliasMiddleware('hasRole', \ITHilbert\UserAuth\Http\Middleware\hasRole::class);
        $this->app['router']->aliasMiddleware('isAdmin', \ITHilbert\UserAuth\Http\Middleware\isAdmin::class);
        $this->app['router']->aliasMiddleware('isDev', \ITHilbert\UserAuth\Http\Middleware\isDev::class);
    }


    public function publishAssets()
    {
        $this->publishes([
            __DIR__ .'/Resources/assets' => public_path('vendor/userauth'),
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
            __DIR__ .'/Config/config.php' => config_path('userauth.php'),
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
            __DIR__ .'/Resources/views' => resource_path('views/vendor/userauth'),
            __DIR__ .'/Resources/views/layouts/userauth.blade.php' => resource_path('views/layouts/userauth.blade.php'),
        ]);

        if(config('userauth.view') == 'ressources'){
            $this->loadViewsFrom(resource_path('Resources/views/vendor/userauth'), 'userauth');
        }else{
            $this->loadViewsFrom(__DIR__ .'/Resources/views', 'userauth');
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

        if(config('userauth.view') == 'ressources'){
            $this->loadTranslationsFrom( resource_path('/Resources/lang/vendor/userauth'), 'userauth');
        }else{
            $this->loadTranslationsFrom( __DIR__ .'/Resources/lang', 'userauth');
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
            $bladeCompiler->directive('hasRole', function ($role, $guard= '') {
                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasRole({$role})): ?>";
            });
            /* hasRoleNot */
            $bladeCompiler->directive('hasRoleNot', function ($role, $guard= '') {
                return "<?php if(auth({$guard})->check() && !auth({$guard})->user()->hasRole({$role})): ?>";
            });
            /* elsehasRole */
            $bladeCompiler->directive('elsehasRole', function ($role, $guard= '') {
                return "<?php elseif(auth({$guard})->check() && auth({$guard})->user()->hasRole({$role})): ?>";
            });
            /* endhasRole */
            $bladeCompiler->directive('endhasRole', function () {
                return '<?php endif; ?>';
            });

            /* hasRoleOr */
            $bladeCompiler->directive('hasRoleOr', function ($roles, $guard= '') {
                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasRoleOr({$roles})): ?>";
            });
            /* elsehasRole */
            $bladeCompiler->directive('elsehasRoleOr', function ($roles, $guard= '') {
                return "<?php elseif(auth({$guard})->check() && auth({$guard})->user()->hasRoleOr({$roles})): ?>";
            });
            /* endhasRoleOr */
            $bladeCompiler->directive('endhasRoleOr', function () {
                return '<?php endif; ?>';
            });

            /* ##################################################### */
            /* hasPermission */
            $bladeCompiler->directive('hasPermission', function ($arguments, $guard= '') {
                list($permission, $guard) = explode(',', $arguments.',');
                return "<?php if(auth({$guard})->check() && auth({$guard})->user()->hasPermission({$permission})): ?>";
            });
            /* hasPermissionNot */
            $bladeCompiler->directive('hasPermissionNot', function ($arguments, $guard= '') {
                list($permission, $guard) = explode(',', $arguments.',');
                return "<?php if(auth({$guard})->check() && !auth({$guard})->user()->hasPermission({$permission})): ?>";
            });
            /* elsehasPermission */
            $bladeCompiler->directive('elsehasPermission', function ($arguments, $guard= '') {
                list($permission, $guard) = explode(',', $arguments.',');
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
