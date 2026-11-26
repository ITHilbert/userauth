<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Oidc;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Infrastructure\Oidc\OidcHttpClientFactory;
use ITHilbert\UserAuth\Tests\Fakes\FakeOidcHttpClientFactory;
use ITHilbert\UserAuth\Tests\Support\NachrichtenAbfangen;
use ITHilbert\UserAuth\Tests\Support\OidcTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Ablauf der OIDC-Anmeldung über die HTTP-Routen mit dem echten Adapter und einem Fake-Anbieter (AK3 bis AK8, AK13,
 * AK14, AK16, Randfall 3, 5). Die Zuordnung zu Benutzern steht in OidcZuordnungTest, die Token-Prüfung im Detail
 * in OidcIdentityProviderTest.
 */
final class OidcAblaufTest extends TestCase
{
    use NachrichtenAbfangen;
    use OidcTestHilfe;
    use RefreshDatabase;

    private FakeOidcHttpClientFactory $anbieter;

    private Model $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oidcKonfigurieren();
        $this->anbieter = new FakeOidcHttpClientFactory;
        $this->app->instance(OidcHttpClientFactory::class, $this->anbieter);
        $this->user = $this->makeUser(['email' => 'max@firma.de']);
        $this->oidcSitzungStarten();
        $this->logSammeln();
    }

    /**
     * Voller Durchlauf: Redirect (state/nonce in der Sitzung), dann Callback mit Code.
     *
     * @param  array<string, string|null>  $abweichung  Callback-Parameter, die vom gültigen Aufruf abweichen (null entfernt)
     */
    private function anmelden(array $abweichung = []): TestResponse
    {
        [$state, $nonce] = $this->oidcStart();
        $this->anbieter->nonce($nonce);

        return $this->oidcCallback(array_filter(
            array_merge(['code' => 'CODE-1', 'state' => $state], $abweichung),
            fn (?string $wert): bool => $wert !== null,
        ));
    }

    private function assertAnmeldungMoeglich(int $tokenAufrufeBisher): void
    {
        $this->anmelden()->assertRedirect('/');
        $this->assertAuthenticatedAs($this->user);
        $this->assertCount($tokenAufrufeBisher + 1, $this->anbieter->requestsTo('token'));
    }

    // AK3
    public function test_ak3_redirect_leitet_per_discovery_auf_den_authorization_endpoint(): void
    {
        $antwort = $this->get(route('userauth.oidc.redirect'));

        $ziel = (string) $antwort->headers->get('Location');
        $this->assertStringStartsWith(FakeOidcHttpClientFactory::URLS['authorization'].'?', $ziel);
        parse_str((string) parse_url($ziel, PHP_URL_QUERY), $parameter);
        $this->assertSame('code', $parameter['response_type']);
        $this->assertSame(FakeOidcHttpClientFactory::CLIENT_ID, $parameter['client_id']);
        $this->assertSame(route('userauth.oidc.callback'), $parameter['redirect_uri']);
        $this->assertSame('openid profile email groups', $parameter['scope']);
        $this->assertNotEmpty($parameter['state']);
        $this->assertNotEmpty($parameter['nonce']);
        $this->assertNotSame($parameter['state'], $parameter['nonce']);
        $antwort->assertSessionHas('userauth.oidc.state', $parameter['state']);
        $antwort->assertSessionHas('userauth.oidc.nonce', $parameter['nonce']);

        $discovery = $this->anbieter->requestsTo('discovery');
        $this->assertCount(1, $discovery);
        $this->assertSame(FakeOidcHttpClientFactory::ISSUER.'/.well-known/openid-configuration', $discovery[0]['url']);
    }

    // AK3
    public function test_ak3_zwei_aufrufe_erzeugen_verschiedene_state_und_nonce(): void
    {
        [$state1, $nonce1] = $this->oidcStart();
        [$state2, $nonce2] = $this->oidcStart();

        $this->assertNotSame($state1, $state2);
        $this->assertNotSame($nonce1, $nonce2);
    }

    /**
     * @return array<string, array{0: callable(FakeOidcHttpClientFactory): void}>
     */
    public static function discoveryFehler(): array
    {
        return [
            'Fehlerstatus 500' => [fn ($a) => $a->antwort('discovery', new Response(500, [], 'kaputt'))],
            'nicht erreichbar' => [fn ($a) => $a->antwort('discovery', new ConnectException('Verbindung verweigert', new Request('GET', 'x')))],
            'ohne authorization_endpoint' => [fn ($a) => $a->discovery(['authorization_endpoint' => null])],
            'ohne token_endpoint' => [fn ($a) => $a->discovery(['token_endpoint' => null])],
            'ohne jwks_uri' => [fn ($a) => $a->discovery(['jwks_uri' => null])],
            'ohne userinfo_endpoint' => [fn ($a) => $a->discovery(['userinfo_endpoint' => null])],
        ];
    }

    // AK3
    #[DataProvider('discoveryFehler')]
    public function test_ak3_fehlerhafte_discovery_fuehrt_auf_lokales_formular(callable $aufbau): void
    {
        $this->get(route('userauth.oidc.redirect'))->assertRedirectContains(FakeOidcHttpClientFactory::URLS['authorization']);

        $aufbau($this->anbieter);

        $antwort = $this->get(route('userauth.oidc.redirect'));

        $this->assertAbgelehnt($antwort);
        $this->assertStringNotContainsString(FakeOidcHttpClientFactory::ISSUER, implode(' ', $this->fehlermeldungen()));
    }

    // AK3 (Fehlermeldung kommt bis in die Seite)
    public function test_ak3_fehlermeldung_steht_auf_der_login_seite(): void
    {
        config()->set('userauth.theme', 'tailwind');
        $this->anbieter->antwort('discovery', new Response(500));

        $this->get(route('userauth.oidc.redirect'))->assertSessionHasErrors();
        $meldung = $this->fehlermeldungen()[0];

        $this->get($this->loginLokalUrl())->assertOk()->assertSee(e($meldung), false);
    }

    /**
     * @return array<string, array{0: array<string, string|null>|null}>
     */
    public static function ungueltigerState(): array
    {
        return [
            'state fehlt' => [['state' => null]],
            'state passt nicht' => [['state' => 'falscher-state']],
            'Sitzung ohne state' => [null],
        ];
    }

    // AK4
    #[DataProvider('ungueltigerState')]
    public function test_ak4_ungueltiger_state_meldet_nicht_an_und_ruft_den_token_endpunkt_nicht_auf(?array $abweichung): void
    {
        if ($abweichung === null) {
            $antwort = $this->oidcCallback(['code' => 'CODE-1', 'state' => 'irgendein-state']);
        } else {
            $antwort = $this->anmelden($abweichung);
        }

        $this->assertAbgelehnt($antwort);
        $this->assertSame([], $this->anbieter->requestsTo('token'));

        // Gegenprobe: mit gültigem state klappt derselbe Aufbau
        $this->assertAnmeldungMoeglich(tokenAufrufeBisher: 0);
    }

    // AK4
    public function test_ak4_state_ist_nach_dem_callback_verbraucht(): void
    {
        $this->anbieter->antwort('token', new Response(500));
        [$state, $nonce] = $this->oidcStart();
        $this->anbieter->nonce($nonce);

        $this->assertAbgelehnt($this->oidcCallback(['code' => 'CODE-1', 'state' => $state]));
        $this->assertCount(1, $this->anbieter->requestsTo('token'), 'Der erste Callback hätte den Token-Endpunkt erreichen müssen.');
        $this->assertNull(session('userauth.oidc.state'));

        // Anbieter antwortet jetzt gültig, der state wird aber ein zweites Mal verwendet
        $this->anbieter->antwort('token', new Response(200, [], json_encode([
            'access_token' => 'AT-2', 'token_type' => 'Bearer', 'id_token' => $this->anbieter->signiert(),
        ], JSON_THROW_ON_ERROR)));

        $this->assertAbgelehnt($this->oidcCallback(['code' => 'CODE-1', 'state' => $state]));
        $this->assertCount(1, $this->anbieter->requestsTo('token'), 'Der zweite Callback mit demselben state erreicht den Token-Endpunkt.');
    }

    /**
     * @return array<string, array{0: callable(string): array<string, string>}>
     */
    public static function fehlerhafteCallbacks(): array
    {
        return [
            'error access_denied' => [fn (string $state) => ['error' => 'access_denied', 'state' => $state]],
            'error trotz code' => [fn (string $state) => ['error' => 'access_denied', 'code' => 'CODE-1', 'state' => $state]],
            'ohne code' => [fn (string $state) => ['state' => $state]],
            'leerer code' => [fn (string $state) => ['code' => '', 'state' => $state]],
        ];
    }

    // AK5
    #[DataProvider('fehlerhafteCallbacks')]
    public function test_ak5_error_oder_fehlender_code_meldet_nicht_an_und_ruft_den_token_endpunkt_nicht_auf(callable $query): void
    {
        [$state] = $this->oidcStart();

        $this->assertAbgelehnt($this->oidcCallback($query($state)));
        $this->assertSame([], $this->anbieter->requestsTo('token'));

        // Gegenprobe: mit Code und neuem state geht derselbe Weg
        $this->assertAnmeldungMoeglich(tokenAufrufeBisher: 0);
    }

    /**
     * @return array<string, array{0: callable(FakeOidcHttpClientFactory): void}>
     */
    public static function tokenFehler(): array
    {
        return [
            'Token-Endpunkt Fehlerstatus' => [fn ($a) => $a->antwort('token', new Response(400, [], '{"error":"invalid_grant"}'))],
            'ohne id_token' => [fn ($a) => $a->antwort('token', new Response(200, [], '{"access_token":"AT","token_type":"Bearer"}'))],
            'ohne access_token' => [fn ($a) => $a->antwort('token', new Response(200, [], '{"id_token":"a.b.c"}'))],
            'Token-Endpunkt nicht erreichbar' => [fn ($a) => $a->antwort('token', new ConnectException('weg', new Request('POST', 'x')))],
        ];
    }

    // AK6
    #[DataProvider('tokenFehler')]
    public function test_ak6_fehler_beim_code_tausch_meldet_nicht_an(callable $aufbau): void
    {
        $aufbau($this->anbieter);

        $this->assertAbgelehnt($this->anmelden());
        $this->assertCount(1, $this->anbieter->requestsTo('token'), 'Der Code-Tausch wurde gar nicht versucht.');
    }

    // AK7
    public function test_ak7_ungueltiges_id_token_meldet_nicht_an_und_verbraucht_die_nonce(): void
    {
        [$state, $nonce] = $this->oidcStart();
        $this->anbieter->nonce($nonce)->idToken($this->anbieter->signiert([], FakeOidcHttpClientFactory::KID, true));

        $this->assertAbgelehnt($this->oidcCallback(['code' => 'CODE-1', 'state' => $state]));
        $this->assertCount(1, $this->anbieter->requestsTo('token'));
        $this->assertSame([], $this->anbieter->requestsTo('userinfo'), 'Userinfo wird mit einem ungültigen id_token abgerufen.');
        $this->assertNull(session('userauth.oidc.nonce'));
        $this->assertNull(session('userauth.oidc.state'));
    }

    // AK7, AK8, AK6
    public function test_erfolgreicher_ablauf_meldet_an_verknuepft_und_verbraucht_state_und_nonce(): void
    {
        $this->anmelden()->assertRedirect('/');

        $this->assertAuthenticatedAs($this->user);
        $this->assertNull(session('userauth.oidc.state'));
        $this->assertNull(session('userauth.oidc.nonce'));
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $this->user->getKey(),
            'provider' => 'oidc',
            'provider_user_id' => 'sub-1',
        ]);
        $this->assertCount(1, $this->anbieter->requestsTo('token'));
        $this->assertCount(1, $this->anbieter->requestsTo('userinfo'));
        $this->assertNotEmpty($this->anbieter->requestsTo('jwks'));
    }

    // Randfall 3
    public function test_randfall3_nicht_vorhandene_ca_datei_gibt_allgemeine_fehlermeldung_statt_500(): void
    {
        [$state, $nonce] = $this->oidcStart();
        $this->anbieter->nonce($nonce);
        config()->set('userauth.oidc.ca_bundle', '/gibt/es/nicht/lan-root-ca.pem');

        $this->assertAbgelehnt($this->oidcCallback(['code' => 'CODE-1', 'state' => $state]));
        $this->assertAbgelehnt($this->get(route('userauth.oidc.redirect')));
        $this->assertStringNotContainsString('lan-root-ca', implode(' ', $this->fehlermeldungen()));

        // Gegenprobe: ohne die falsche Einstellung läuft der Ablauf
        config()->set('userauth.oidc.ca_bundle', '');
        $this->assertAnmeldungMoeglich(tokenAufrufeBisher: 0);
    }

    // AK13
    public function test_ak13_oidc_verlangt_keinen_zwei_faktor_code_das_passwort_weiterhin(): void
    {
        config()->set('userauth.two_factor_enabled', true);
        $this->nachrichtenAbfangen();

        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])
            ->assertRedirect(route('verify.index'));
        $this->assertGuest();
        $codeMails = count($this->gesendeteNachrichten());
        $this->assertSame(1, $codeMails, 'Der Passwort-Login hat keinen Code verschickt (Aufbau greift nicht).');

        $this->anmelden()->assertRedirect('/');

        $this->assertAuthenticatedAs($this->user);
        $this->assertCount($codeMails, $this->gesendeteNachrichten(), 'Die OIDC-Anmeldung hat einen Zwei-Faktor-Code verschickt.');
    }

    // AK14
    public function test_ak14_weiterleitung_geht_an_intended_sonst_an_redirect_after_login(): void
    {
        $this->withSession(['url.intended' => url('/ziel-davor')]);
        $this->anmelden()->assertRedirect(url('/ziel-davor'));
        $this->assertAuthenticatedAs($this->user);
    }

    // AK14
    public function test_ak14_ohne_intended_gilt_redirect_after_login(): void
    {
        config()->set('userauth.redirect_after_login', '/startseite');

        $this->anmelden()->assertRedirect(url('/startseite'));
        $this->assertAuthenticatedAs($this->user);
    }

    // AK14
    public function test_ak14_sitzung_wird_neu_erzeugt_und_login_ereignis_entsteht(): void
    {
        config()->set('userauth.audit_log_enabled', true);
        $angemeldet = [];
        Event::listen(Login::class, function (Login $ereignis) use (&$angemeldet): void {
            $angemeldet[] = $ereignis->user->getAuthIdentifier();
        });

        $this->anmelden()->assertRedirect('/');

        $this->assertAuthenticatedAs($this->user);
        $this->assertNotSame($this->sitzungsId, session()->getId(), 'Die Sitzungs-ID bleibt nach der Anmeldung gleich (Session-Fixation).');
        $this->assertSame([$this->user->getKey()], $angemeldet);
        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'login')->where('user_id', $this->user->getKey())->count());
    }

    // Randfall 5
    public function test_randfall5_angemeldeter_benutzer_im_callback_verknuepft_nichts(): void
    {
        config()->set('userauth.redirect_after_login', '/startseite');
        $angemeldet = $this->makeUser(['email' => 'anderer@firma.de']);
        [$state, $nonce] = $this->oidcStart();
        $this->anbieter->nonce($nonce);

        $this->actingAs($angemeldet)
            ->get(route('userauth.oidc.callback', ['code' => 'CODE-1', 'state' => $state]))
            ->assertRedirect(url('/startseite'));

        $this->assertAuthenticatedAs($angemeldet);
        $this->assertSame(0, DB::table('user_social_accounts')->count());
        $this->assertSame([], $this->anbieter->requestsTo('token'));
    }

    // AK16
    public function test_ak16_fehler_enthalten_weder_secret_noch_token_noch_code_und_stehen_im_log(): void
    {
        $fremdesToken = $this->anbieter->signiert([], FakeOidcHttpClientFactory::KID, true);
        $geheimnisse = ['SECRET-geheim-123', 'AT-geheim-1', $fremdesToken, 'CODE-1'];
        $ausgaben = [];

        $szenarien = [
            fn () => $this->oidcCallback(['code' => 'CODE-1', 'state' => 'falsch']),
            fn () => $this->oidcCallback(['error' => 'access_denied', 'state' => 'falsch']),
            function () use ($fremdesToken) {
                $this->anbieter->idToken($fremdesToken);

                return $this->anmelden();
            },
            function () {
                $this->anbieter->antwort('token', new Response(400, [], '{"error":"invalid_client","client_secret":"SECRET-geheim-123"}'));

                return $this->anmelden();
            },
        ];

        foreach ($szenarien as $lauf) {
            $this->logTexte = [];
            $antwort = $lauf();
            $this->assertAbgelehnt($antwort);
            $this->assertNotEmpty($this->logTexte, 'Der Fehler steht nicht im Log.');
            $ausgaben[] = $antwort->getContent().' '.implode(' ', $this->fehlermeldungen()).' '.implode(' ', $this->logTexte);
            $ausgaben[] = $this->get($this->loginLokalUrl())->getContent();
        }

        foreach ($ausgaben as $ausgabe) {
            foreach ($geheimnisse as $geheimnis) {
                $this->assertStringNotContainsString($geheimnis, $ausgabe, 'Geheimnis in Meldung, Antwort oder Log.');
            }
        }
    }

    // AK16
    public function test_ak16_meldungen_sind_in_de_und_en_uebersetzt_und_verschieden(): void
    {
        $texte = [];
        foreach (['de', 'en'] as $sprache) {
            app()->setLocale($sprache);
            $this->assertAbgelehnt($this->oidcCallback(['error' => 'access_denied', 'state' => 'x']));
            $meldungen = $this->fehlermeldungen();
            $this->assertNotEmpty($meldungen);
            foreach ($meldungen as $meldung) {
                $this->assertStringNotContainsString('::', $meldung, 'Übersetzungsschlüssel statt Text.');
                $this->assertDoesNotMatchRegularExpression('/^[a-z_.]+$/', $meldung, 'Übersetzungsschlüssel statt Text.');
            }
            $texte[$sprache] = $meldungen;
        }

        $this->assertNotSame($texte['de'], $texte['en']);
    }
}
