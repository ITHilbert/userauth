<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Oidc;

use GuzzleHttp\Psr7\Response;
use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcIdentityProvider;
use ITHilbert\UserAuth\Application\Oidc\Exceptions\OidcException;
use ITHilbert\UserAuth\Application\Oidc\OidcIdentity;
use ITHilbert\UserAuth\Infrastructure\Oidc\OidcHttpClientFactory;
use ITHilbert\UserAuth\Tests\Fakes\FakeOidcHttpClientFactory;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Adapter zum Anbieter: Token-Aufruf, id_token-Prüfung, Userinfo, Identität (AK6, AK7, AK8, Randfall 1, 2).
 * Gegenstand ist der Port; HTTP wird über die Client-Fabrik im Container ersetzt.
 */
final class OidcIdentityProviderTest extends TestCase
{
    private FakeOidcHttpClientFactory $anbieter;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('userauth.oidc.enabled', true);
        config()->set('userauth.oidc.issuer', FakeOidcHttpClientFactory::ISSUER);
        config()->set('userauth.oidc.client_id', FakeOidcHttpClientFactory::CLIENT_ID);
        config()->set('userauth.oidc.client_secret', 'SECRET-geheim-123');
        config()->set('userauth.oidc.ca_bundle', '');

        $this->anbieter = new FakeOidcHttpClientFactory;
        $this->app->instance(OidcHttpClientFactory::class, $this->anbieter);
    }

    private function adapter(): OidcIdentityProvider
    {
        return $this->app->make(OidcIdentityProvider::class);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $userinfo
     */
    private function identitaet(array $claims = [], array $userinfo = []): OidcIdentity
    {
        $this->anbieter->claims($claims)->userinfo($userinfo);

        return $this->adapter()->fetchIdentity('CODE-1', 'nonce-1');
    }

    // AK6
    public function test_ak6_token_aufruf_ist_post_mit_formfeldern_client_secret_post(): void
    {
        $this->identitaet();

        $aufrufe = $this->anbieter->requestsTo('token');
        $this->assertCount(1, $aufrufe);
        $this->assertSame('POST', $aufrufe[0]['method']);
        $this->assertSame([
            'grant_type' => 'authorization_code',
            'code' => 'CODE-1',
            'redirect_uri' => route('userauth.oidc.callback'),
            'client_id' => FakeOidcHttpClientFactory::CLIENT_ID,
            'client_secret' => 'SECRET-geheim-123',
        ], $this->anbieter->tokenForm());
    }

    // AK6
    public function test_ak6_ca_bundle_gilt_als_verify_fuer_alle_aufrufe_sonst_standard(): void
    {
        $this->identitaet();
        foreach (['discovery', 'token', 'jwks', 'userinfo'] as $key) {
            $this->assertNotEmpty($this->anbieter->requestsTo($key), "Kein Aufruf {$key} ohne ca_bundle.");
            $this->assertTrue($this->anbieter->requestsTo($key)[0]['verify'], "{$key}: ohne ca_bundle gelten die Standard-Zertifikate.");
        }

        $bundle = tempnam(sys_get_temp_dir(), 'ca');
        file_put_contents($bundle, "kein echtes Zertifikat\n");
        config()->set('userauth.oidc.ca_bundle', $bundle);
        $this->anbieter->requests = [];

        try {
            $this->adapter()->authorizationUrl('S', 'N');
            $this->adapter()->fetchIdentity('CODE-1', 'nonce-1');
        } finally {
            @unlink($bundle);
        }

        foreach (['discovery', 'token', 'jwks', 'userinfo'] as $key) {
            $this->assertNotEmpty($this->anbieter->requestsTo($key), "Kein Aufruf {$key} mit ca_bundle.");
            foreach ($this->anbieter->requestsTo($key) as $aufruf) {
                $this->assertSame($bundle, $aufruf['verify'], "{$key}: ca_bundle ist nicht die Guzzle-Option verify.");
            }
        }
    }

    /**
     * @return array<string, array{0: Response}>
     */
    public static function fehlerhafteTokenAntworten(): array
    {
        return [
            'Fehlerstatus 400' => [new Response(400, [], '{"error":"invalid_grant"}')],
            'Fehlerstatus 500' => [new Response(500, [], 'kaputt')],
            'ohne id_token' => [new Response(200, [], '{"access_token":"AT","token_type":"Bearer"}')],
            'ohne access_token' => [new Response(200, [], '{"id_token":"x.y.z","token_type":"Bearer"}')],
            'kein JSON' => [new Response(200, [], 'kein json')],
        ];
    }

    // AK6
    #[DataProvider('fehlerhafteTokenAntworten')]
    public function test_ak6_fehlerhafte_token_antwort_ist_oidc_exception(Response $antwort): void
    {
        $this->anbieter->antwort('token', $antwort);

        $this->expectException(OidcException::class);

        $this->adapter()->fetchIdentity('CODE-1', 'nonce-1');
    }

    // AK6
    public function test_ak6_gueltige_token_antwort_liefert_identitaet_gegenprobe(): void
    {
        $this->assertSame('sub-1', $this->identitaet()->subject);
    }

    /**
     * @return array<string, array{0: callable(FakeOidcHttpClientFactory): void}>
     */
    public static function ungueltigeIdTokens(): array
    {
        $f = FakeOidcHttpClientFactory::class;

        return [
            'falsche Signatur (anderer Schlüssel)' => [fn ($a) => $a->idToken($a->signiert([], $f::KID, true))],
            'Algorithmus none' => [fn ($a) => $a->idToken($a->ohneSignatur())],
            'Algorithmus HS256 mit öffentlichem Schlüssel' => [fn ($a) => $a->idToken($a->hs256MitOeffentlichemSchluessel())],
            'falscher iss' => [fn ($a) => $a->idToken($a->signiert(['iss' => 'https://boese.example.test']))],
            'falsche aud' => [fn ($a) => $a->idToken($a->signiert(['aud' => 'andere-app']))],
            'aud-Array ohne client_id (Randfall 2)' => [fn ($a) => $a->idToken($a->signiert(['aud' => ['andere-app', 'dritte-app']]))],
            'abgelaufen (über Toleranz)' => [fn ($a) => $a->idToken($a->signiert(['exp' => time() - 120, 'iat' => time() - 600]))],
            'nonce fehlt' => [fn ($a) => $a->idToken($a->signiert(['nonce' => null]))],
            'nonce falsch' => [fn ($a) => $a->idToken($a->signiert(['nonce' => 'fremde-nonce']))],
            'unbekannte kid' => [fn ($a) => $a->idToken($a->signiert([], 'unbekannt'))],
            'kein JWT' => [fn ($a) => $a->idToken('das-ist-kein-jwt')],
        ];
    }

    // AK7
    #[DataProvider('ungueltigeIdTokens')]
    public function test_ak7_ungueltiges_id_token_ist_oidc_exception(callable $aufbau): void
    {
        // Gegenprobe: derselbe Aufbau mit gültigem Token liefert die Identität
        $this->assertSame('sub-1', $this->adapter()->fetchIdentity('CODE-1', 'nonce-1')->subject);

        $aufbau($this->anbieter);

        $this->expectException(OidcException::class);

        $this->adapter()->fetchIdentity('CODE-1', 'nonce-1');
    }

    // AK7
    public function test_ak7_abgelaufenes_token_innerhalb_von_60_sekunden_toleranz_ist_gueltig(): void
    {
        $this->anbieter->idToken($this->anbieter->signiert(['exp' => time() - 30, 'iat' => time() - 400]));

        $this->assertSame('sub-1', $this->adapter()->fetchIdentity('CODE-1', 'nonce-1')->subject);
    }

    // Randfall 2
    public function test_randfall2_aud_als_array_ist_gueltig_wenn_client_id_enthalten(): void
    {
        $this->anbieter->idToken($this->anbieter->signiert(['aud' => ['andere-app', FakeOidcHttpClientFactory::CLIENT_ID]]));

        $this->assertSame('sub-1', $this->adapter()->fetchIdentity('CODE-1', 'nonce-1')->subject);
    }

    // AK7 (Gegenprobe der falschen nonce: die erwartete nonce kommt vom Aufrufer)
    public function test_ak7_nonce_wird_mit_dem_wert_des_aufrufers_verglichen(): void
    {
        $this->anbieter->nonce('andere-nonce');

        $this->assertSame('sub-1', $this->adapter()->fetchIdentity('CODE-1', 'andere-nonce')->subject);
    }

    // AK8
    public function test_ak8_userinfo_wird_per_bearer_abgerufen_und_mit_dem_id_token_zusammengefuehrt(): void
    {
        $identitaet = $this->identitaet(
            claims: ['email' => 'token@firma.de'],
            userinfo: ['email' => null, 'name' => 'Max Muster', 'groups' => ['admins', 'devs']],
        );

        $aufrufe = $this->anbieter->requestsTo('userinfo');
        $this->assertCount(1, $aufrufe);
        $this->assertSame('GET', $aufrufe[0]['method']);
        $this->assertSame('Bearer AT-geheim-1', $aufrufe[0]['authorization']);
        $this->assertSame('sub-1', $identitaet->subject);
        $this->assertSame('token@firma.de', $identitaet->email, 'E-Mail aus dem id_token geht beim Zusammenführen verloren.');
        $this->assertSame('Max Muster', $identitaet->name, 'Name aus dem Userinfo fehlt.');
        $this->assertSame(['admins', 'devs'], $identitaet->groups);
    }

    // AK8
    public function test_ak8_abweichender_sub_im_userinfo_ist_oidc_exception(): void
    {
        $this->assertSame('sub-1', $this->identitaet()->subject);

        $this->anbieter->userinfo(['sub' => 'sub-ANDERER']);

        $this->expectException(OidcException::class);

        $this->adapter()->fetchIdentity('CODE-1', 'nonce-1');
    }

    // AK8
    public function test_ak8_name_faellt_auf_preferred_username_zurueck(): void
    {
        $this->assertSame('maxm', $this->identitaet(userinfo: ['name' => null, 'preferred_username' => 'maxm'])->name);
        $this->assertSame('Max Muster', $this->identitaet(userinfo: ['name' => 'Max Muster', 'preferred_username' => 'maxm'])->name);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function unbrauchbareEmail(): array
    {
        return [
            'email fehlt' => [['email' => null]],
            'email_verified false' => [['email_verified' => false]],
        ];
    }

    // AK8
    #[DataProvider('unbrauchbareEmail')]
    public function test_ak8_fehlende_oder_unbestaetigte_email_ist_oidc_exception(array $userinfo): void
    {
        $this->assertSame('max@firma.de', $this->identitaet()->email);

        $this->anbieter->userinfo($userinfo);

        $this->expectException(OidcException::class);

        $this->adapter()->fetchIdentity('CODE-1', 'nonce-1');
    }

    // AK8
    public function test_ak8_fehlendes_email_verified_gilt_als_bestaetigt_ausdruecklich_true_ebenso(): void
    {
        $this->assertSame('max@firma.de', $this->identitaet(userinfo: ['email_verified' => null])->email);
        $this->assertSame('max@firma.de', $this->identitaet(userinfo: ['email_verified' => true])->email);
    }

    /**
     * @return array<string, array{0: mixed, 1: list<string>}>
     */
    public static function gruppen(): array
    {
        return [
            'fehlt' => [null, []],
            'String statt Array' => ['admins', []],
            'Zahl' => [5, []],
            'Liste' => [['admins', 'devs'], ['admins', 'devs']],
        ];
    }

    // Randfall 1
    #[DataProvider('gruppen')]
    public function test_randfall1_groups_fehlt_oder_ist_kein_array_ergibt_leere_liste(mixed $groups, array $erwartet): void
    {
        $identitaet = $this->identitaet(userinfo: ['groups' => $groups]);

        $this->assertSame($erwartet, $identitaet->groups);
    }
}
