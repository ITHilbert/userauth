<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Oidc;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Infrastructure\Oidc\OidcHttpClientFactory;
use ITHilbert\UserAuth\Tests\Fakes\FakeOidcHttpClientFactory;
use ITHilbert\UserAuth\Tests\Support\OidcTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Auto-Redirect als Standard, Ziel-Adresse, Abmeldung und Abmeldeseite (AK21 bis AK23, #2226).
 * Der Standard kommt aus src/Config/config.php (der Aufbau setzt ihn nicht von Hand auf true).
 */
final class OidcAutoRedirectStandardTest extends TestCase
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
        config()->set('userauth.redirect_after_logout', '/abschied');
        config()->set('userauth.theme', 'tailwind');
        $this->oidcSitzungStarten();
    }

    /**
     * Wert aus der Config-Datei des Pakets, optional mit gesetzter Umgebungsvariable.
     */
    private function dateiStandard(?string $env = null): bool
    {
        $name = 'USERAUTH_OIDC_AUTO_REDIRECT';
        $vorher = getenv($name);

        try {
            $env === null ? putenv($name) : putenv("{$name}={$env}");

            return (bool) (require dirname(__DIR__, 3).'/src/Config/config.php')['oidc']['auto_redirect'];
        } finally {
            $vorher === false ? putenv($name) : putenv("{$name}={$vorher}");
        }
    }

    private function standardUebernehmen(): void
    {
        config()->set('userauth.oidc.auto_redirect', $this->dateiStandard());
    }

    // AK21
    public function test_ak21_standard_leitet_gast_weiter_und_env_false_zeigt_das_formular(): void
    {
        $this->standardUebernehmen();

        $this->get(route('login'))->assertRedirect(route('userauth.oidc.redirect'));
        $this->get(route('login', ['lokal' => 1]))->assertOk()->assertSee('name="password"', false);

        // Gegenprobe: Env false schaltet ab, dann Formular samt Knopf
        config()->set('userauth.oidc.auto_redirect', $this->dateiStandard('false'));
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('userauth.oidc.redirect'), false)
            ->assertSee('name="password"', false);
    }

    // AK21
    public function test_ak21_ohne_aktives_oidc_bleibt_login_unveraendert_trotz_standard(): void
    {
        $this->standardUebernehmen();
        $this->get(route('login'))->assertRedirect(route('userauth.oidc.redirect'));

        config()->set('userauth.oidc.enabled', false);

        $this->get(route('login'))->assertOk()->assertSee('name="password"', false);
    }

    // AK21
    public function test_ak21_ziel_adresse_bleibt_ueber_die_weiterleitung_erhalten(): void
    {
        $this->standardUebernehmen();
        $user = $this->makeUser(['email' => 'max@firma.de']);
        Route::middleware(['web', 'auth'])->get('/_t/geschuetzt', fn () => 'geschuetzt');

        $this->get('/_t/geschuetzt')->assertRedirect(route('login'));
        $this->get(route('login'))->assertRedirect(route('userauth.oidc.redirect'));

        [$state, $nonce] = $this->oidcStart();
        $this->anbieter->nonce($nonce);

        $this->oidcCallback(['code' => 'CODE-1', 'state' => $state])->assertRedirect(url('/_t/geschuetzt'));
        $this->assertAuthenticatedAs($user);
    }

    // AK22
    public function test_ak22_abmelden_fuehrt_mit_auto_redirect_auf_abgemeldet_sonst_wie_bisher(): void
    {
        $this->standardUebernehmen();
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $abgemeldet = route('login', ['abgemeldet' => 1]);

        $this->actingAs($user)->post('/logout')->assertRedirect($abgemeldet);
        $this->assertGuest();

        $this->post('/logout')->assertRedirect($abgemeldet);

        // Gegenprobe: Auto-Redirect aus, dann wie bisher; derselbe Aufbau mit Auto-Redirect ging oben auf abgemeldet
        config()->set('userauth.oidc.auto_redirect', false);
        $this->actingAs($user)->post('/logout')->assertRedirect('/abschied');
        $this->post('/logout')->assertRedirect('/abschied');
    }

    // AK22
    #[DataProvider('themes')]
    public function test_ak22_abgemeldet_seite_leitet_nicht_weiter_und_zeigt_knopf_und_formular(string $theme): void
    {
        $this->standardUebernehmen();
        config()->set('userauth.theme', $theme);
        config()->set('userauth.oidc.button_text', 'Anmelden-Knopf-Probe');

        $this->get(route('login'))->assertRedirect(route('userauth.oidc.redirect'));

        $this->get(route('login', ['abgemeldet' => 1]))
            ->assertOk()
            ->assertSee('Anmelden-Knopf-Probe')
            ->assertSee('href="'.route('userauth.oidc.redirect').'"', false)
            ->assertSee('name="password"', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function themes(): array
    {
        return ['tailwind' => ['tailwind'], 'bootstrap' => ['bootstrap']];
    }

    // AK22
    public function test_ak22_abmeldemeldung_ist_in_de_und_en_vorhanden_und_verschieden(): void
    {
        $this->standardUebernehmen();

        $meldungen = [];
        foreach (['de', 'en'] as $sprache) {
            app()->setLocale($sprache);
            $lokal = $this->zeilen($this->get(route('login', ['lokal' => 1]))->assertOk()->getContent());
            $abgemeldet = $this->zeilen($this->get(route('login', ['abgemeldet' => 1]))->assertOk()->getContent());

            $meldungen[$sprache] = array_values(array_diff($abgemeldet, $lokal));
            $this->assertNotEmpty($meldungen[$sprache], "Die Seite ?abgemeldet=1 zeigt auf {$sprache} nichts, was ?lokal=1 nicht zeigt.");
        }

        $this->assertNotSame($meldungen['de'], $meldungen['en'], 'Die Abmeldemeldung ist nicht übersetzt.');
    }

    /**
     * @return list<string> Zeilen ohne CSRF-Token
     */
    private function zeilen(string $html): array
    {
        return array_map('trim', explode("\n", (string) preg_replace('/name="_token" value="[^"]*"/', 'name="_token"', $html)));
    }

    // AK23
    public function test_ak23_readme_changelog_und_config_beschreiben_den_neuen_standard(): void
    {
        $wurzel = dirname(__DIR__, 3);
        $abschnitt = strstr((string) file_get_contents($wurzel.'/README.md'), 'Anmeldung über Authelia');
        $this->assertNotFalse($abschnitt, 'Abschnitt „Anmeldung über Authelia“ fehlt in der README.');

        foreach (['USERAUTH_OIDC_AUTO_REDIRECT', 'lokal=1', 'abgemeldet=1'] as $stichwort) {
            $this->assertStringContainsString($stichwort, $abschnitt, "README-Abschnitt nennt {$stichwort} nicht.");
        }
        $this->assertMatchesRegularExpression(
            '/USERAUTH_OIDC_AUTO_REDIRECT[^\n]*\btrue\b|\btrue\b[^\n]*USERAUTH_OIDC_AUTO_REDIRECT/',
            $abschnitt,
            'Die README nennt true nicht als Wert von USERAUTH_OIDC_AUTO_REDIRECT.',
        );

        // Abschnitt der Einführungsversion (4.3.0), unabhängig vom Abstand zum Dateianfang
        $this->assertSame(
            1,
            preg_match('/^## 4\.3\.0\b.*?(?=^## |\z)/ms', (string) file_get_contents($wurzel.'/CHANGELOG.md'), $treffer),
            'Abschnitt 4.3.0 fehlt im CHANGELOG.',
        );
        $changelog = $treffer[0];
        $this->assertStringContainsString('USERAUTH_OIDC_AUTO_REDIRECT', $changelog);
        $this->assertStringContainsString('abgemeldet', $changelog);

        $config = (string) file_get_contents($wurzel.'/src/Config/config.php');
        $this->assertMatchesRegularExpression("/'USERAUTH_OIDC_AUTO_REDIRECT',\\s*true\\)/", $config);
        $this->assertStringContainsString('lokal=1', $config);
    }
}
