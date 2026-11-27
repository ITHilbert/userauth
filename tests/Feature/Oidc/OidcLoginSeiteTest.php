<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Oidc;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcIdentityProvider;
use ITHilbert\UserAuth\Application\Oidc\HandleOidcLogin;
use ITHilbert\UserAuth\Infrastructure\Oidc\OidcHttpClientFactory;
use ITHilbert\UserAuth\Tests\Fakes\FakeOidcHttpClientFactory;
use ITHilbert\UserAuth\Tests\Support\OidcTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Login-Seite, Schalter, Routen und Paketdateien der OIDC-Anmeldung (AK1, AK2, AK15, AK17, AK18).
 */
final class OidcLoginSeiteTest extends TestCase
{
    use OidcTestHilfe;
    use RefreshDatabase;

    private FakeOidcHttpClientFactory $anbieter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oidcKonfigurieren();
        $this->anbieter = new FakeOidcHttpClientFactory;
        $this->app->instance(OidcHttpClientFactory::class, $this->anbieter);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function themes(): array
    {
        return ['tailwind' => ['tailwind'], 'bootstrap' => ['bootstrap']];
    }

    // AK1
    public function test_ak1_routen_liefern_404_und_login_bleibt_wie_zuvor_wenn_oidc_aus(): void
    {
        config()->set('userauth.oidc.enabled', false);
        config()->set('userauth.social.enabled', true);
        config()->set('userauth.theme', 'tailwind');
        $user = $this->makeUser(['email' => 'max@firma.de']);

        $this->get('/auth/oidc/redirect')->assertNotFound();
        $this->get('/auth/oidc/callback')->assertNotFound();
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee(route('userauth.oidc.redirect'), false);
        $this->assertSame([], $this->anbieter->requests, 'Bei ausgeschaltetem OIDC darf kein Aufruf zum Anbieter gehen.');

        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    // AK1 Gegenprobe: dieselbe Konfiguration mit enabled = true bedient die Route
    public function test_ak1_gegenprobe_route_antwortet_nicht_404_wenn_oidc_an(): void
    {
        $this->get('/auth/oidc/redirect')->assertRedirect();
    }

    // AK2
    #[DataProvider('themes')]
    public function test_ak2_login_zeigt_link_mit_button_text_und_formular(string $theme): void
    {
        config()->set('userauth.theme', $theme);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('userauth.oidc.redirect'), false)
            ->assertSee('Mit IT-Hilbert anmelden')
            ->assertSee('name="password"', false);

        config()->set('userauth.oidc.button_text', 'Weiter mit Authelia');
        $this->get(route('login'))->assertSee('Weiter mit Authelia');
    }

    // AK2
    public function test_ak2_ohne_konfiguration_kein_knopf_und_redirect_fuehrt_auf_lokales_formular(): void
    {
        config()->set('userauth.theme', 'tailwind');
        config()->set('userauth.oidc.client_secret', '');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee(route('userauth.oidc.redirect'), false);

        $antwort = $this->get(route('userauth.oidc.redirect'));

        $antwort->assertRedirect($this->loginLokalUrl());
        $antwort->assertSessionHasErrors();
        $this->assertSame([], $this->anbieter->requests, 'Ohne Konfiguration darf kein Aufruf zum Anbieter gehen.');
        $this->assertSame([], array_filter(
            $this->fehlermeldungen(),
            fn (string $m): bool => str_contains($m, 'auth.example.test') || str_contains($m, 'client-app'),
        ), 'Die Fehlermeldung nennt Werte der Konfiguration.');
    }

    // AK2
    public function test_ak2_jeder_fehlende_pflichtwert_blendet_den_knopf_aus(): void
    {
        config()->set('userauth.theme', 'tailwind');
        $this->get(route('login'))->assertSee(route('userauth.oidc.redirect'), false);

        foreach (['issuer', 'client_id', 'client_secret'] as $schluessel) {
            $wert = config("userauth.oidc.{$schluessel}");
            foreach ([null, ''] as $leer) {
                config()->set("userauth.oidc.{$schluessel}", $leer);
                $this->get(route('login'))->assertOk()->assertDontSee(route('userauth.oidc.redirect'), false);
            }
            config()->set("userauth.oidc.{$schluessel}", $wert);
        }
    }

    // AK15
    public function test_ak15_auto_redirect_leitet_gast_auf_oidc_und_lokal_zeigt_formular(): void
    {
        config()->set('userauth.oidc.auto_redirect', true);
        config()->set('userauth.theme', 'tailwind');

        $this->get(route('login'))->assertRedirect(route('userauth.oidc.redirect'));

        $this->get(route('login', ['lokal' => 1]))
            ->assertOk()
            ->assertSee(route('userauth.oidc.redirect'), false)
            ->assertSee('name="password"', false);
    }

    // AK15
    public function test_ak15_auto_redirect_greift_nicht_ohne_aktives_konfiguriertes_oidc(): void
    {
        config()->set('userauth.oidc.auto_redirect', true);
        $this->get(route('login'))->assertRedirect(route('userauth.oidc.redirect'));

        config()->set('userauth.oidc.enabled', false);
        $this->get(route('login'))->assertOk();

        config()->set('userauth.oidc.enabled', true);
        config()->set('userauth.oidc.issuer', '');
        $this->get(route('login'))->assertOk();
    }

    // AK15
    public function test_ak15_angemeldete_und_post_login_sind_nicht_betroffen(): void
    {
        config()->set('userauth.oidc.auto_redirect', true);
        $user = $this->makeUser(['email' => 'max@firma.de']);

        $this->get(route('login'))->assertRedirect(route('userauth.oidc.redirect'));

        // POST /login bleibt der normale Passwort-Login
        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        // Angemeldete werden wie bisher von der Login-Seite weggeleitet, nicht zu OIDC
        $ziel = $this->get(route('login'))->assertRedirect()->headers->get('Location');
        $this->assertStringNotContainsString('/auth/oidc/redirect', (string) $ziel);
    }

    // AK15
    public function test_ak15_fehlerweiterleitungen_fuehren_auf_lokal_und_nicht_in_eine_schleife(): void
    {
        config()->set('userauth.oidc.auto_redirect', true);

        $this->get(route('userauth.oidc.callback', ['error' => 'access_denied']))
            ->assertRedirect($this->loginLokalUrl());

        $this->get($this->loginLokalUrl())->assertOk();
    }

    // AK17
    public function test_ak17_routen_tragen_die_namen_und_pfade_und_gehen_den_provider_routen_vor(): void
    {
        $this->assertSame('/auth/oidc/redirect', route('userauth.oidc.redirect', [], false));
        $this->assertSame('/auth/oidc/callback', route('userauth.oidc.callback', [], false));

        $routen = app('router')->getRoutes();
        foreach (['redirect', 'callback'] as $ziel) {
            $treffer = $routen->match(Request::create("/auth/oidc/{$ziel}", 'GET'));
            $this->assertSame("userauth.oidc.{$ziel}", $treffer->getName());
        }

        // Routen sind auch bei ausgeschaltetem OIDC registriert
        config()->set('userauth.oidc.enabled', false);
        $this->assertTrue(Route::has('userauth.oidc.redirect'));
        $this->assertTrue(Route::has('userauth.oidc.callback'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function oidcRouten(): array
    {
        return ['redirect' => ['userauth.oidc.redirect'], 'callback' => ['userauth.oidc.callback']];
    }

    // AK17
    #[DataProvider('oidcRouten')]
    public function test_ak17_routen_sind_gedrosselt_wie_die_social_routen(string $routenname): void
    {
        // Schritt 1: die Route antwortet ohne Drosselung (kein 429 beim ersten Aufruf)
        $this->get(route($routenname, ['error' => 'x']))->assertStatus(302);

        $gedrosselt = null;
        for ($i = 2; $i <= 40; $i++) {
            if ($this->get(route($routenname, ['error' => 'x']))->status() === 429) {
                $gedrosselt = $i;
                break;
            }
        }

        $this->assertNotNull($gedrosselt, 'Auch nach 40 Aufrufen in einer Minute kommt kein 429.');
        $this->assertLessThanOrEqual(31, $gedrosselt, 'Die Drosselung greift später als bei den Social-Routen (30 je Minute).');
    }

    // AK18
    public function test_ak18_config_enthaelt_den_block_oidc_mit_allen_schluesseln_und_standardwerten(): void
    {
        $standard = require dirname(__DIR__, 3).'/src/Config/config.php';

        $this->assertIsArray($standard['oidc'] ?? null, 'Der Block oidc fehlt in config.php.');
        $this->assertEqualsCanonicalizing(
            ['enabled', 'issuer', 'client_id', 'client_secret', 'ca_bundle', 'button_text', 'auto_create', 'default_role', 'group_roles', 'auto_redirect'],
            array_keys($standard['oidc']),
        );
        $this->assertFalse($standard['oidc']['enabled']);
        $this->assertFalse($standard['oidc']['auto_create']);
        $this->assertTrue($standard['oidc']['auto_redirect']);
        $this->assertSame('Mit IT-Hilbert anmelden', $standard['oidc']['button_text']);
        $this->assertSame('user', $standard['oidc']['default_role']);
        foreach (['issuer', 'client_id', 'client_secret', 'ca_bundle', 'group_roles'] as $leer) {
            $this->assertContains($standard['oidc'][$leer], ['', null], "Standard von {$leer} ist nicht leer.");
        }
    }

    // AK18
    public function test_ak18_composer_json_verlangt_php_jwt_und_guzzle(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('^6.4 || ^7.0', $composer['require']['firebase/php-jwt'] ?? null);
        $this->assertArrayHasKey('guzzlehttp/guzzle', $composer['require']);
    }

    // AK18
    public function test_ak18_readme_und_changelog_dokumentieren_die_anmeldung(): void
    {
        $readme = (string) file_get_contents(dirname(__DIR__, 3).'/README.md');
        $abschnitt = strstr($readme, 'Anmeldung über Authelia');

        $this->assertNotFalse($abschnitt, 'Abschnitt „Anmeldung über Authelia“ fehlt in der README.');
        foreach ([
            'USERAUTH_OIDC_ENABLED', 'USERAUTH_OIDC_ISSUER', 'USERAUTH_OIDC_CLIENT_ID', 'USERAUTH_OIDC_CLIENT_SECRET',
            'USERAUTH_OIDC_CA_BUNDLE', 'USERAUTH_OIDC_BUTTON_TEXT', 'USERAUTH_OIDC_AUTO_CREATE', 'USERAUTH_OIDC_DEFAULT_ROLE',
            'USERAUTH_OIDC_GROUP_ROLES', 'USERAUTH_OIDC_AUTO_REDIRECT', '/auth/oidc/callback', 'lan-root-ca', 'lokal=1',
        ] as $stichwort) {
            $this->assertStringContainsString($stichwort, $abschnitt, "README-Abschnitt nennt {$stichwort} nicht.");
        }

        // Der Abschnitt der Einführungsversion (4.1.0) nennt die Anmeldung; unabhängig vom Abstand zum Dateianfang
        $changelog = (string) file_get_contents(dirname(__DIR__, 3).'/CHANGELOG.md');
        $this->assertSame(1, preg_match('/^## 4\.1\.0\b.*?(?=^## |\z)/ms', $changelog, $treffer), 'Abschnitt 4.1.0 fehlt im CHANGELOG.');
        $this->assertMatchesRegularExpression('/oidc|authelia/i', $treffer[0]);
    }

    // AK19
    public function test_ak19_oidc_folgt_der_schichtung_port_in_application_adapter_in_infrastructure(): void
    {
        $this->assertTrue(interface_exists(OidcIdentityProvider::class));
        $this->assertTrue(class_exists(HandleOidcLogin::class));

        $adapter = $this->app->make(OidcIdentityProvider::class);

        $this->assertStringStartsWith('ITHilbert\\UserAuth\\Infrastructure\\Oidc\\', $adapter::class);
    }
}
