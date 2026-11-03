<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Router;
use Illuminate\Routing\SortedMiddleware;
use ITHilbert\UserAuth\Http\Middleware\EnsureSocialLoginEnabled;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Bugfix #594: Der Schalter `userauth.social.enabled` greift vor der CSRF-Prüfung der Gruppe `web`.
 * Aus: alle Social-Login-Routen antworten auf jede Methode mit 404 statt 419. An: ohne Token weiterhin 419.
 *
 * Laravel überspringt die CSRF-Prüfung, wenn die Umgebung `testing` ist; die Umgebung wird deshalb auf `local`
 * gesetzt, damit die Prüfung wirkt (Muster aus PostRoutenTest).
 */
final class SocialSchalterVorCsrfTest extends SocialLoginTestCase
{
    private const TOKEN = 'gueltiges-csrf-token-1234567890abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'local';
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function postRouten(): array
    {
        return [
            'POST confirm' => ['POST', '/auth/social/confirm/irgendein-token'],
            'DELETE social-accounts' => ['DELETE', '/social-accounts/google'],
        ];
    }

    // AK-Bugfix #594
    public function test_schalter_aus_post_confirm_ohne_csrf_token_ergibt_404(): void
    {
        // Gegenprobe: die CSRF-Prüfung wirkt (Schalter an, ohne Token → 419)
        $this->post('/auth/social/confirm/irgendein-token')->assertStatus(419);

        config()->set('userauth.social.enabled', false);

        $this->post('/auth/social/confirm/irgendein-token')->assertNotFound();
    }

    // AK-Bugfix #594
    #[DataProvider('postRouten')]
    public function test_schalter_aus_schreibende_social_routen_ohne_token_ergeben_404(string $methode, string $uri): void
    {
        // Gegenprobe: bei „an" lehnt CSRF ab, die Route existiert und wird erreicht
        $this->call($methode, $uri)->assertStatus(419);

        config()->set('userauth.social.enabled', false);

        $this->call($methode, $uri)->assertNotFound();
    }

    // AK-Bugfix #594
    public function test_schalter_aus_post_confirm_mit_gueltigem_token_ergibt_404(): void
    {
        config()->set('userauth.social.enabled', false);
        $this->withSession(['_token' => self::TOKEN]);

        $this->post('/auth/social/confirm/irgendein-token', ['_token' => self::TOKEN])->assertNotFound();
    }

    // AK-Bugfix #594
    public function test_schalter_an_post_confirm_ohne_token_wird_von_csrf_abgelehnt(): void
    {
        $this->post('/auth/social/confirm/irgendein-token')->assertStatus(419);
    }

    // AK-Bugfix #594
    public function test_schalter_an_post_confirm_mit_gueltigem_token_passiert_csrf(): void
    {
        $this->withSession(['_token' => self::TOKEN]);

        $antwort = $this->post('/auth/social/confirm/irgendein-token', ['_token' => self::TOKEN]);

        $this->assertNotSame(419, $antwort->getStatusCode());
    }

    // AK-Bugfix #594
    public function test_schalter_steht_in_der_sortierten_middleware_der_route_vor_der_csrf_pruefung(): void
    {
        // Der HTTP-Kernel überträgt beim Erzeugen seine Gruppen (mit CSRF-Prüfung) und Priorität auf den Router
        $this->app->make(HttpKernel::class);
        $router = $this->app->make(Router::class);
        $route = $router->getRoutes()->getByName('userauth.social.confirm.store');
        $this->assertNotNull($route);

        $sortiert = (new SortedMiddleware(
            $router->middlewarePriority,
            $router->gatherRouteMiddleware($route),
        ))->all();

        $schalter = array_search(EnsureSocialLoginEnabled::class, $sortiert, true);
        $this->assertNotFalse($schalter, 'EnsureSocialLoginEnabled fehlt in der Middleware der Route');

        $csrf = null;
        foreach ($sortiert as $stelle => $middleware) {
            if (is_string($middleware) && is_a($middleware, PreventRequestForgery::class, true)) {
                $csrf = $stelle;
                break;
            }
        }
        $this->assertNotNull($csrf, 'CSRF-Middleware fehlt in der Gruppe web (Gegenprobe)');

        $this->assertLessThan($csrf, $schalter);
    }
}
