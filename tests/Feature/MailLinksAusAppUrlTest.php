<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Application\Passwort\PasswortZuruecksetzenAnfordern;
use ITHilbert\UserAuth\Application\SocialLogin\IssueSocialLinkConfirmation;
use ITHilbert\UserAuth\Mail\SocialLinkConfirmation;
use ITHilbert\UserAuth\Tests\Feature\SocialLogin\SocialLoginTestCase;
use ITHilbert\UserAuth\Tests\Support\NachrichtenAbfangen;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Links in Mails entstehen aus app.url, nicht aus Host und Schema der Anfrage (userauth-mail-links, Dashboard #154).
 * Abgedeckt: AK1–AK4 und beide Randfälle. AK5 (README) ist nicht testbar.
 */
final class MailLinksAusAppUrlTest extends SocialLoginTestCase
{
    use NachrichtenAbfangen;

    private const ANGREIFER = 'angreifer.example';

    private const ADRESSE = 'max@firma.de';

    /** @var array<string, string> */
    private const PROXY_KOPFZEILEN = [
        'X-Forwarded-Host' => self::ANGREIFER,
        'X-Forwarded-Proto' => 'http',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->nachrichtenAbfangen();
        config()->set('app.url', 'https://app.example');
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        SymfonyRequest::setTrustedProxies([], 0);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- Hilfen

    /**
     * Lage "App vertraut dem Absender als Proxy" mit Gegenprobe: url() liefert in der Anfrage wirklich den Angreifer-Host.
     */
    private function proxyVertrauen(): void
    {
        TrustProxies::at('*');

        Route::get('/_url-probe', fn (): string => url('/').'|'.route('login'))->middleware('web');

        $antwort = $this->get('/_url-probe', self::PROXY_KOPFZEILEN)->assertOk();
        $this->assertStringStartsWith(
            'http://'.self::ANGREIFER,
            (string) $antwort->getContent(),
            'Aufbau greift nicht: Die App übernimmt X-Forwarded-Host nicht, die Prüfung wäre wirkungslos.',
        );
        $this->assertStringContainsString('|http://'.self::ANGREIFER.'/', (string) $antwort->getContent());
    }

    /**
     * @param  array<string, string>  $kopfzeilen
     */
    private function resetAnfordern(string $url = '/password/sendtocken', array $kopfzeilen = []): TestResponse
    {
        return $this->post($url, ['email' => self::ADRESSE], $kopfzeilen);
    }

    private function resetLink(): string
    {
        $nachrichten = $this->nachrichtenAn(self::ADRESSE);
        $this->assertNotEmpty($nachrichten, 'Es wurde keine Reset-Mail versendet.');
        $link = $this->linkAusNachricht(end($nachrichten));
        $this->assertNotNull($link, 'Die Reset-Mail enthält keinen Link auf password.editwithtoken.');

        return $link;
    }

    /**
     * Stellt die Bestätigung der Kontoverknüpfung aus und liefert den Link der Mail.
     *
     * @param  array<string, string>  $kopfzeilen
     */
    private function bestaetigungsLink(string $url = '', array $kopfzeilen = []): string
    {
        $this->makeUser(['email' => self::ADRESSE], verified: false);
        $this->provider->returnIdentity('google-sub-1', self::ADRESSE);

        $this->get($url !== '' ? $url : route('userauth.social.callback', 'google'), $kopfzeilen);

        $links = [];
        foreach ([Mail::sent(Mailable::class), Mail::queued(Mailable::class)] as $mails) {
            foreach ($mails as $mail) {
                if ($mail instanceof SocialLinkConfirmation) {
                    $links[] = $mail->confirmUrl;
                }
            }
        }
        $this->assertNotEmpty($links, 'Es wurde keine Bestätigungsmail für die Kontoverknüpfung ausgestellt.');

        return $links[0];
    }

    /**
     * @return list<array{0: string|null}>
     */
    public static function leereAppUrl(): array
    {
        return [
            'nicht gesetzt' => [null],
            'leer gesetzt' => [''],
        ];
    }

    // ---------------------------------------------------------------- AK1

    // AK1
    public function test_reset_link_beginnt_mit_app_url_trotz_gefaelschtem_proxy_host(): void
    {
        $this->makeUser(['email' => self::ADRESSE]);
        $this->proxyVertrauen();

        $this->resetAnfordern('/password/sendtocken', self::PROXY_KOPFZEILEN)->assertRedirect();

        $link = $this->resetLink();
        $this->assertStringStartsWith('https://app.example/', $link);
    }

    // AK1
    public function test_reset_link_enthaelt_angreifer_host_nicht(): void
    {
        $this->makeUser(['email' => self::ADRESSE]);
        $this->proxyVertrauen();

        $this->resetAnfordern('/password/sendtocken', self::PROXY_KOPFZEILEN)->assertRedirect();

        $this->assertStringNotContainsString(self::ANGREIFER, $this->resetLink());
    }

    // AK1
    public function test_reset_link_entspricht_der_route_mit_token_und_email(): void
    {
        $this->makeUser(['email' => self::ADRESSE]);
        $this->proxyVertrauen();

        $this->resetAnfordern('/password/sendtocken', self::PROXY_KOPFZEILEN)->assertRedirect();

        $link = $this->resetLink();
        $this->assertStringStartsWith('https://app.example/', $link, 'Link beginnt nicht mit app.url.');
        $this->assertSame(1, preg_match('~/password/editwithtoken/([^/]+)/([^/]+)$~', $link, $teile));
        $erwartet = route('password.editwithtoken', ['token' => urldecode($teile[1]), 'email' => self::ADRESSE], false);
        $this->assertSame('https://app.example'.$erwartet, $link);
    }

    // ---------------------------------------------------------------- AK2

    // AK2
    public function test_bestaetigungslink_beginnt_mit_app_url_trotz_gefaelschtem_proxy_host(): void
    {
        $this->proxyVertrauen();

        $link = $this->bestaetigungsLink(kopfzeilen: self::PROXY_KOPFZEILEN);

        $this->assertStringStartsWith('https://app.example/', $link);
    }

    // AK2
    public function test_bestaetigungslink_enthaelt_angreifer_host_nicht(): void
    {
        $this->proxyVertrauen();

        $link = $this->bestaetigungsLink(kopfzeilen: self::PROXY_KOPFZEILEN);

        $this->assertStringNotContainsString(self::ANGREIFER, $link);
    }

    // AK2
    public function test_bestaetigungslink_fuehrt_auf_die_bestaetigungsroute(): void
    {
        $this->proxyVertrauen();

        $link = $this->bestaetigungsLink(kopfzeilen: self::PROXY_KOPFZEILEN);

        $this->assertStringStartsWith('https://app.example/', $link, 'Link beginnt nicht mit app.url.');
        $token = basename((string) parse_url($link, PHP_URL_PATH));
        $this->assertSame('https://app.example'.route('userauth.social.confirm', $token, false), $link);
    }

    // ---------------------------------------------------------------- AK3

    // AK3
    public function test_beide_klassen_teilen_einen_gebundenen_application_contract_mit_infrastructure_adapter(): void
    {
        $klassen = [
            PasswortZuruecksetzenAnfordern::class,
            IssueSocialLinkConfirmation::class,
        ];

        $vertraege = [];
        foreach ($klassen as $klasse) {
            $vertraege[$klasse] = [];
            foreach ((new ReflectionClass($klasse))->getConstructor()->getParameters() as $parameter) {
                $typ = $parameter->getType();
                if ($typ instanceof ReflectionNamedType
                    && ! $typ->isBuiltin()
                    && interface_exists($typ->getName())
                    && str_starts_with($typ->getName(), 'ITHilbert\\UserAuth\\Application\\')) {
                    $vertraege[$klasse][] = $typ->getName();
                }
            }
        }

        $gemeinsam = array_values(array_intersect($vertraege[$klassen[0]], $vertraege[$klassen[1]]));
        $this->assertNotEmpty($gemeinsam, 'Beide Klassen teilen keinen Contract aus dem Application-Layer für den Mail-Link.');

        foreach ($gemeinsam as $vertrag) {
            $this->assertTrue($this->app->bound($vertrag), "{$vertrag} ist nicht im Container gebunden.");
            $this->assertStringStartsWith(
                'ITHilbert\\UserAuth\\Infrastructure\\',
                $this->app->make($vertrag)::class,
                "Adapter von {$vertrag} liegt nicht in Infrastructure.",
            );
        }
    }

    // AK3
    public function test_aufrufer_rufen_url_generator_route_fuer_die_links_nicht_selbst_auf(): void
    {
        $basis = dirname(__DIR__, 2).'/src/Application/';

        foreach (['Passwort/PasswortZuruecksetzenAnfordern.php', 'SocialLogin/IssueSocialLinkConfirmation.php'] as $datei) {
            $code = (string) file_get_contents($basis.$datei);
            $this->assertNotSame('', $code, "{$datei} nicht lesbar.");
            $this->assertSame(0, substr_count($code, '->route('), "{$datei} erzeugt den Link noch selbst über ->route().");
        }
    }

    // AK3
    public function test_paket_setzt_keine_globale_root_url(): void
    {
        $treffer = [];
        $anzahl = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));
        foreach ($iterator as $datei) {
            if ($datei->isFile() && $datei->getExtension() === 'php') {
                $anzahl++;
            }
            if ($datei->isFile() && $datei->getExtension() === 'php'
                && str_contains((string) file_get_contents($datei->getPathname()), 'forceRootUrl')) {
                $treffer[] = $datei->getPathname();
            }
        }

        $this->assertGreaterThan(50, $anzahl, 'Gegenprobe: Es wurden kaum Paketdateien durchsucht.');
        $this->assertSame([], $treffer, 'forceRootUrl wird im Paket verwendet.');
    }

    // AK3
    public function test_link_erzeugung_aendert_die_globale_url_einstellung_nicht(): void
    {
        $this->makeUser(['email' => self::ADRESSE]);
        $this->proxyVertrauen();

        $this->resetAnfordern('/password/sendtocken', self::PROXY_KOPFZEILEN)->assertRedirect();
        $this->assertStringStartsWith('https://app.example/', $this->resetLink(), 'Link beginnt nicht mit app.url.');

        $danach = $this->get('/_url-probe', self::PROXY_KOPFZEILEN)->assertOk();
        $this->assertStringStartsWith(
            'http://'.self::ANGREIFER,
            (string) $danach->getContent(),
            'Die Link-Erzeugung hat die globale Root-URL verändert.',
        );
    }

    // ---------------------------------------------------------------- AK4

    /**
     * @return array<string, array{0: string}>
     */
    public static function appUrlMitUnterpfad(): array
    {
        return [
            'ohne Schrägstrich' => ['https://example.com/app'],
            'mit Schrägstrich' => ['https://example.com/app/'],
        ];
    }

    // AK4
    #[DataProvider('appUrlMitUnterpfad')]
    public function test_reset_link_bei_app_url_mit_unterpfad(string $appUrl): void
    {
        config()->set('app.url', $appUrl);
        $this->makeUser(['email' => self::ADRESSE]);

        $this->resetAnfordern()->assertRedirect();

        $this->assertStringStartsWith('https://example.com/app/password/', $this->resetLink());
    }

    // AK4
    #[DataProvider('appUrlMitUnterpfad')]
    public function test_reset_link_bei_unterpfad_hat_keinen_doppelten_schraegstrich(string $appUrl): void
    {
        config()->set('app.url', $appUrl);
        $this->makeUser(['email' => self::ADRESSE]);

        $this->resetAnfordern()->assertRedirect();

        $link = $this->resetLink();
        $this->assertStringStartsWith('https://example.com/app/', $link, 'Link beginnt nicht mit app.url.');
        $this->assertStringNotContainsString('//', substr($link, strlen('https://')));
    }

    // ---------------------------------------------------------------- Randfall: app.url leer

    // Randfall app.url leer oder nicht gesetzt: Link entsteht aus der Anfrage
    #[DataProvider('leereAppUrl')]
    public function test_reset_link_entsteht_bei_leerer_app_url_aus_der_anfrage(?string $appUrl): void
    {
        config()->set('app.url', $appUrl);
        $this->makeUser(['email' => self::ADRESSE]);

        $this->resetAnfordern('http://anfrage.example/password/sendtocken')->assertRedirect();

        $this->assertStringStartsWith('http://anfrage.example/', $this->resetLink());
    }

    // Randfall app.url leer oder nicht gesetzt: Warnung im Log
    #[DataProvider('leereAppUrl')]
    public function test_reset_schreibt_bei_leerer_app_url_eine_warnung_ins_log(?string $appUrl): void
    {
        config()->set('app.url', $appUrl);
        $this->makeUser(['email' => self::ADRESSE]);
        Log::spy();

        $this->resetAnfordern('http://anfrage.example/password/sendtocken')->assertRedirect();

        $this->assertStringStartsWith('http://anfrage.example/', $this->resetLink(), 'Die Anfrage ging verloren.');
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    // Randfall app.url leer oder nicht gesetzt: Bestätigungslink entsteht aus der Anfrage
    #[DataProvider('leereAppUrl')]
    public function test_bestaetigungslink_entsteht_bei_leerer_app_url_aus_der_anfrage(?string $appUrl): void
    {
        config()->set('app.url', $appUrl);

        $link = $this->bestaetigungsLink('http://anfrage.example/'.ltrim(route('userauth.social.callback', 'google', false), '/'));

        $this->assertStringStartsWith('http://anfrage.example/', $link);
    }

    // Randfall app.url leer oder nicht gesetzt: Warnung im Log
    #[DataProvider('leereAppUrl')]
    public function test_bestaetigung_schreibt_bei_leerer_app_url_eine_warnung_ins_log(?string $appUrl): void
    {
        config()->set('app.url', $appUrl);
        Log::spy();

        $link = $this->bestaetigungsLink('http://anfrage.example/'.ltrim(route('userauth.social.callback', 'google', false), '/'));

        $this->assertStringStartsWith('http://anfrage.example/', $link, 'Die Anfrage ging verloren.');
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    // Randfall (Gegenprobe): bei gesetzter app.url keine Warnung
    public function test_bei_gesetzter_app_url_keine_warnung_im_log(): void
    {
        $this->makeUser(['email' => self::ADRESSE]);
        Log::spy();

        $this->resetAnfordern()->assertRedirect();

        $this->assertStringStartsWith('https://app.example/', $this->resetLink(), 'Link beginnt nicht mit app.url.');
        Log::shouldNotHaveReceived('warning');
    }

    // ---------------------------------------------------------------- Randfall: kein Proxy-Header

    // Randfall Kein Proxy-Header: Link wie AK1 aus app.url, auch wenn Anfrage-Host abweicht
    public function test_reset_link_ohne_proxy_header_kommt_aus_app_url_trotz_abweichendem_anfrage_host(): void
    {
        $this->makeUser(['email' => self::ADRESSE]);

        $this->resetAnfordern('http://anfrage.example/password/sendtocken')->assertRedirect();

        $link = $this->resetLink();
        $this->assertStringStartsWith('https://app.example/', $link);
        $this->assertStringNotContainsString('anfrage.example', $link);
    }

    // Randfall Kein Proxy-Header: Bestätigungslink aus app.url
    public function test_bestaetigungslink_ohne_proxy_header_kommt_aus_app_url_trotz_abweichendem_anfrage_host(): void
    {
        $link = $this->bestaetigungsLink('http://anfrage.example/'.ltrim(route('userauth.social.callback', 'google', false), '/'));

        $this->assertStringStartsWith('https://app.example/', $link);
        $this->assertStringNotContainsString('anfrage.example', $link);
    }
}
