<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ITHilbert\UserAuth\Http\Middleware\hasPermission;
use ITHilbert\UserAuth\Http\Middleware\hasPermissionAnd;
use ITHilbert\UserAuth\Http\Middleware\hasPermissionOr;
use ITHilbert\UserAuth\Http\Middleware\hasRole;
use ITHilbert\UserAuth\Http\Middleware\isAdmin;
use ITHilbert\UserAuth\Http\Middleware\isDev;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Rechte-Middlewares laufen vor dem Route-Model-Binding (userauth-rechte-prioritaet, #434).
 * Jeder Weg wird mit Kernel nach dem Boot (Testbench) und vor dem Boot (public/index.php) geprüft.
 */
final class RechtePrioritaetTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_OHNE_RECHTE = 3;

    private const ROLLE_REDAKTEUR = 4;

    private const ROLLE_NUR_X = 5;

    private const ROLLE_X_UND_Y = 6;

    private const FEHLENDE_ID = 987654;

    /** @var list<class-string> */
    private const RECHTE_MIDDLEWARES = [
        hasPermission::class,
        hasPermissionAnd::class,
        hasPermissionOr::class,
        hasRole::class,
        isAdmin::class,
        isDev::class,
    ];

    /**
     * Ein anderes Paket trägt seine Middleware in seinem Provider direkt in die Router-Gruppe `web` ein
     * (vor dem Provider von userauth).
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [FremdeGruppenMiddlewareProvider::class, ...parent::getPackageProviders($app)];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $jetzt = now();
        foreach ([
            1 => ['dev', 'Developer'],
            2 => ['admin', 'Admin'],
            self::ROLLE_OHNE_RECHTE => ['user', 'Anwender'],
            self::ROLLE_REDAKTEUR => ['redakteur', 'Redakteur'],
            self::ROLLE_NUR_X => ['nur-x', 'Nur X'],
            self::ROLLE_X_UND_Y => ['x-und-y', 'X und Y'],
        ] as $id => [$name, $anzeige]) {
            DB::table('roles')->insert([
                'id' => $id, 'role' => $name, 'role_display' => $anzeige,
                'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => null,
            ]);
        }

        foreach (['x' => 1, 'y' => 2] as $recht => $id) {
            DB::table('permissions')->insert([
                'id' => $id, 'permission' => $recht, 'permission_display' => strtoupper($recht),
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
        DB::table('role_permission')->insert([
            ['role_id' => self::ROLLE_NUR_X, 'permission_id' => 1],
            ['role_id' => self::ROLLE_X_UND_Y, 'permission_id' => 1],
            ['role_id' => self::ROLLE_X_UND_Y, 'permission_id' => 2],
        ]);
    }

    /**
     * Wie public/index.php: der HTTP-Kernel wird vor dem Boot der Provider aufgelöst.
     */
    protected function kernelVorDemBoot($app): void
    {
        $app->make(HttpKernelContract::class);
    }

    /**
     * Host hat Kernel vor dem Boot aufgelöst und die Rechte-Middlewares selbst vor SubstituteBindings gesetzt
     * (Übergangslösung in bootstrap/app.php).
     */
    protected function hostSetztPrioritaetSelbst($app): void
    {
        $kernel = $app->make(HttpKernelContract::class);
        foreach (self::RECHTE_MIDDLEWARES as $middleware) {
            $kernel->addToMiddlewarePriorityBefore(SubstituteBindings::class, $middleware);
        }
    }

    /**
     * @return array<string, array{0: string, 1: int}> Middleware-Angabe, Rolle MIT Zugriff
     */
    public static function rechteMiddlewares(): array
    {
        return [
            'hasPermission' => ['hasPermission:x', self::ROLLE_NUR_X],
            'hasPermissionAnd' => ['hasPermissionAnd:x,y', self::ROLLE_X_UND_Y],
            'hasPermissionOr' => ['hasPermissionOr:x,y', self::ROLLE_NUR_X],
            'hasRole' => ['hasRole:redakteur', self::ROLLE_REDAKTEUR],
            'isAdmin' => ['isAdmin', 2],
            'isDev' => ['isDev', 1],
        ];
    }

    private function probeRoutes(string $middleware): void
    {
        // mit Model-Binding
        Route::middleware(['web', 'auth', $middleware])->get('/probe/{user}', fn (User $user) => 'OK-'.$user->id);
        // ohne Model-Parameter (Referenz für die heutige Ablehnung)
        Route::middleware(['web', 'auth', $middleware])->get('/probe-ohne/{name}', fn (string $name) => 'OK-'.$name);
    }

    private function nutzer(int $rolle): User
    {
        return User::factory()->create(['role_id' => $rolle]);
    }

    private function ziel(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_OHNE_RECHTE]);
    }

    /**
     * @return array{0: int, 1: string|null}
     */
    private function antwort(string $url): array
    {
        $r = $this->get($url);

        return [$r->getStatusCode(), $r->headers->get('Location')];
    }

    private function pfad(?string $url): ?string
    {
        return $url === null ? null : parse_url($url, PHP_URL_PATH);
    }

    // ------------------------------------------------------------ Hilfsprüfungen je Weg

    private function pruefeAblehnungUnabhaengigVonId(string $middleware, int $rolleMit): void
    {
        $this->probeRoutes($middleware);
        $ziel = $this->ziel();

        // Gegenprobe: mit Recht greift der Aufbau (vorhandene ID 200, fehlende 404)
        $this->actingAs($this->nutzer($rolleMit));
        $this->get('/probe/'.$ziel->id)->assertOk();
        $this->get('/probe/'.self::FEHLENDE_ID)->assertNotFound();
        $this->assertNotNull(User::find($ziel->id));
        $this->assertNull(User::find(self::FEHLENDE_ID));

        $this->actingAs($this->nutzer(self::ROLLE_OHNE_RECHTE));
        $referenz = $this->antwort('/probe-ohne/egal');
        $vorhanden = $this->antwort('/probe/'.$ziel->id);
        $fehlend = $this->antwort('/probe/'.self::FEHLENDE_ID);

        // heutige Ablehnung der Middleware: Weiterleitung, nie 200/404
        $this->assertSame(302, $referenz[0]);
        $this->assertSame($referenz, $vorhanden, 'vorhandene ID: Antwort wie Ablehnung ohne Model-Binding');
        $this->assertSame($referenz, $fehlend, 'fehlende ID: Antwort wie Ablehnung ohne Model-Binding');
        $this->assertSame($vorhanden, $fehlend);
    }

    private function pruefeMitRechtFehlendeIdNotFound(string $middleware, int $rolleMit): void
    {
        $this->probeRoutes($middleware);
        $ziel = $this->ziel();
        $this->actingAs($this->nutzer($rolleMit));

        $this->get('/probe/'.self::FEHLENDE_ID)->assertNotFound();
        $this->get('/probe/'.$ziel->id)->assertOk()->assertSee('OK-'.$ziel->id);
    }

    private function pruefeGastZumLogin(string $middleware): void
    {
        $this->probeRoutes($middleware);
        $ziel = $this->ziel();

        $loginPfad = $this->pfad(route('login'));
        $vorhanden = $this->antwort('/probe/'.$ziel->id);
        $fehlend = $this->antwort('/probe/'.self::FEHLENDE_ID);

        $this->assertSame(302, $vorhanden[0]);
        $this->assertSame($loginPfad, $this->pfad($vorhanden[1]));
        $this->assertSame(302, $fehlend[0]);
        $this->assertSame($loginPfad, $this->pfad($fehlend[1]));
        // Gegenprobe: ID existiert wirklich bzw. nicht
        $this->assertNotNull(User::find($ziel->id));
        $this->assertNull(User::find(self::FEHLENDE_ID));
    }

    /**
     * @return list<string>
     */
    private function pruefePrioritaet(): array
    {
        $kernel = $this->app->make(HttpKernelContract::class);
        $listen = [
            'Kernel' => $kernel->getMiddlewarePriority(),
            'Router' => $this->app->make(Router::class)->middlewarePriority,
        ];

        foreach ($listen as $name => $liste) {
            // Gegenprobe: die geprüfte Liste ist nicht leer und kennt Auth und Binding
            $auth = array_search(AuthenticatesRequests::class, $liste, true);
            $binding = array_search(SubstituteBindings::class, $liste, true);
            $this->assertNotFalse($auth, "{$name}-Priorität enthält AuthenticatesRequests");
            $this->assertNotFalse($binding, "{$name}-Priorität enthält SubstituteBindings");

            foreach (self::RECHTE_MIDDLEWARES as $middleware) {
                $this->assertSame(1, count(array_keys($liste, $middleware, true)), "{$name}: {$middleware} genau einmal");
                $stelle = array_search($middleware, $liste, true);
                $this->assertGreaterThan($auth, $stelle, "{$name}: {$middleware} nach AuthenticatesRequests");
                $this->assertLessThan($binding, $stelle, "{$name}: {$middleware} vor SubstituteBindings");
            }
        }

        return $listen['Router'];
    }

    // ------------------------------------------------------------ AK1, AK3 — Kernel nach dem Boot

    // AK1, AK3
    #[DataProvider('rechteMiddlewares')]
    public function test_ablehnung_unabhaengig_von_der_id_kernel_nach_dem_boot(string $middleware, int $rolleMit): void
    {
        $this->pruefeAblehnungUnabhaengigVonId($middleware, $rolleMit);
    }

    // AK1, AK3, AK5
    #[DefineEnvironment('kernelVorDemBoot')]
    #[DataProvider('rechteMiddlewares')]
    public function test_ablehnung_unabhaengig_von_der_id_kernel_vor_dem_boot(string $middleware, int $rolleMit): void
    {
        $this->pruefeAblehnungUnabhaengigVonId($middleware, $rolleMit);
    }

    // ------------------------------------------------------------ AK2, AK3

    // AK2, AK3
    #[DataProvider('rechteMiddlewares')]
    public function test_mit_recht_fehlende_id_404_vorhandene_200_kernel_nach_dem_boot(string $middleware, int $rolleMit): void
    {
        $this->pruefeMitRechtFehlendeIdNotFound($middleware, $rolleMit);
    }

    // AK2, AK3, AK5
    #[DefineEnvironment('kernelVorDemBoot')]
    #[DataProvider('rechteMiddlewares')]
    public function test_mit_recht_fehlende_id_404_vorhandene_200_kernel_vor_dem_boot(string $middleware, int $rolleMit): void
    {
        $this->pruefeMitRechtFehlendeIdNotFound($middleware, $rolleMit);
    }

    // ------------------------------------------------------------ AK4

    // AK4, AK3
    #[DataProvider('rechteMiddlewares')]
    public function test_gast_wird_unabhaengig_von_der_id_zum_login_geleitet_kernel_nach_dem_boot(string $middleware, int $rolleMit): void
    {
        $this->pruefeGastZumLogin($middleware);
    }

    // AK4, AK3, AK5
    #[DefineEnvironment('kernelVorDemBoot')]
    #[DataProvider('rechteMiddlewares')]
    public function test_gast_wird_unabhaengig_von_der_id_zum_login_geleitet_kernel_vor_dem_boot(string $middleware, int $rolleMit): void
    {
        $this->pruefeGastZumLogin($middleware);
    }

    // ------------------------------------------------------------ AK5 — Priorität

    // AK5
    public function test_prioritaet_nach_auth_und_vor_binding_kernel_nach_dem_boot(): void
    {
        $this->pruefePrioritaet();
    }

    // AK5
    #[DefineEnvironment('kernelVorDemBoot')]
    public function test_prioritaet_nach_auth_und_vor_binding_kernel_vor_dem_boot(): void
    {
        $this->assertTrue($this->app->resolved(HttpKernelContract::class), 'Kernel wurde vor dem Boot aufgelöst');
        $this->pruefePrioritaet();
    }

    // ------------------------------------------------------------ Randfälle

    // Randfall 1
    #[DefineEnvironment('hostSetztPrioritaetSelbst')]
    public function test_prioritaet_ohne_doppelten_eintrag_wenn_host_sie_selbst_gesetzt_hat(): void
    {
        $router = $this->pruefePrioritaet();

        $this->assertContains(hasPermission::class, $router);
    }

    // Randfall 1
    #[DefineEnvironment('hostSetztPrioritaetSelbst')]
    public function test_ablehnung_unabhaengig_von_der_id_wenn_host_prioritaet_selbst_gesetzt_hat(): void
    {
        $this->pruefeAblehnungUnabhaengigVonId('hasPermission:x', self::ROLLE_NUR_X);
    }

    // Randfall 2
    #[DataProvider('rechteMiddlewares')]
    public function test_route_ohne_model_parameter_unveraendert(string $middleware, int $rolleMit): void
    {
        $this->probeRoutes($middleware);

        $this->get('/probe-ohne/abc')->assertRedirect();
        $this->assertSame($this->pfad(route('login')), $this->pfad($this->antwort('/probe-ohne/abc')[1]));

        $this->actingAs($this->nutzer($rolleMit));
        $this->get('/probe-ohne/abc')->assertOk()->assertSee('OK-abc');

        $this->actingAs($this->nutzer(self::ROLLE_OHNE_RECHTE));
        $abgelehnt = $this->get('/probe-ohne/abc');
        $this->assertSame(302, $abgelehnt->getStatusCode());
        $this->assertStringNotContainsString('OK-abc', (string) $abgelehnt->getContent());
    }

    // ------------------------------------------------------------ Randfall: fremde Router-Gruppen bleiben erhalten

    private function pruefeFremdeGruppeBleibtErhalten(): void
    {
        // Gegenprobe: die Rechte-Middlewares stehen in der Priorität (Eintragen hat stattgefunden)
        $router = $this->app->make(Router::class);
        $this->app->make(HttpKernelContract::class);
        $this->assertContains(SubstituteBindings::class, $router->middlewarePriority);
        foreach (self::RECHTE_MIDDLEWARES as $middleware) {
            $this->assertContains($middleware, $router->middlewarePriority);
        }

        $this->assertContains(FremdeGruppenMiddleware::class, $router->getMiddlewareGroups()['web'] ?? []);

        Route::middleware('web')->get('/fremd', fn () => 'OK');
        $this->get('/fremd')->assertOk()->assertHeader('X-Fremd', '1');
    }

    // Randfall 3
    public function test_fremde_middleware_in_router_gruppe_bleibt_erhalten_kernel_nach_dem_boot(): void
    {
        $this->pruefeFremdeGruppeBleibtErhalten();
    }

    // Randfall 3
    #[DefineEnvironment('kernelVorDemBoot')]
    public function test_fremde_middleware_in_router_gruppe_bleibt_erhalten_kernel_vor_dem_boot(): void
    {
        $this->pruefeFremdeGruppeBleibtErhalten();
    }
}

/**
 * Probe-Middleware eines anderen Pakets.
 */
final class FremdeGruppenMiddleware
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $antwort = $next($request);
        $antwort->headers->set('X-Fremd', '1');

        return $antwort;
    }
}

/**
 * Provider eines anderen Pakets: trägt seine Middleware direkt in die Router-Gruppe `web` ein.
 */
final class FremdeGruppenMiddlewareProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', FremdeGruppenMiddleware::class);
    }
}
