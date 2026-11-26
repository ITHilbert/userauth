<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Tests\Fakes\FakeSocialIdentityProvider;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Login-Seite mit Passkey-Knopf und das ausgelieferte Skript (AK1 Teil Login-Seite, AK2, Randfälle 1 und 2).
 *
 * Vorgabe an die Seite: der Knopf trägt `data-testid="passkey-login"`, das Skript steht als `<script src>` mit
 * der Adresse der Route `userauth.assets.passkey` auf der Seite.
 */
final class PasskeyLoginSeiteTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
    }

    private function seite(): DOMXPath
    {
        $html = (string) $this->get(route('login'))->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    private function anzahl(DOMXPath $seite, string $abfrage): int
    {
        return $seite->query($abfrage)->length;
    }

    private function knopf(DOMXPath $seite): int
    {
        return $this->anzahl($seite, '//*[@data-testid="passkey-login"]');
    }

    private function passwortFeld(DOMXPath $seite): int
    {
        return $this->anzahl($seite, '//form//input[@name="password"]');
    }

    private function skript(DOMXPath $seite): int
    {
        return $this->anzahl($seite, '//script[contains(@src, "'.route('userauth.assets.passkey', [], false).'")]');
    }

    // AK1
    public function test_ak1_ohne_passkey_zeigt_die_login_seite_keinen_knopf_und_die_passwort_anmeldung_bleibt(): void
    {
        $nutzer = $this->nutzer(['email' => 'max@firma.de']);
        config()->set('userauth.passkey.enabled', false);

        $seite = $this->seite();
        $this->assertSame(0, $this->knopf($seite));
        $this->assertSame(0, $this->skript($seite));
        $this->assertSame(1, $this->passwortFeld($seite));
        $this->post('/login', ['email' => 'max@firma.de', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($nutzer);

        // Gegenprobe: dieselbe Seite zeigt den Knopf, sobald Passkey an ist
        $this->neueSitzung();
        config()->set('userauth.passkey.enabled', true);
        $this->assertSame(1, $this->knopf($this->seite()));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function themesUndModi(): array
    {
        $faelle = [];
        foreach (['tailwind', 'bootstrap'] as $theme) {
            foreach (['immer', 'ohne_passkey'] as $modus) {
                $faelle["{$theme} {$modus}"] = [$theme, $modus];
            }
        }

        return $faelle;
    }

    // AK2
    #[DataProvider('themesUndModi')]
    public function test_ak2_login_seite_zeigt_knopf_skript_und_das_passwortformular_bleibt(string $theme, string $modus): void
    {
        config()->set('userauth.theme', $theme);
        config()->set('userauth.passwort_login', $modus);

        $seite = $this->seite();

        $this->assertSame(1, $this->knopf($seite), 'Der Passkey-Knopf fehlt.');
        $this->assertSame(1, $this->skript($seite));
        $this->assertSame(1, $this->passwortFeld($seite), 'Das Passwortformular fehlt.');
        $this->assertStringContainsString('Mit Passkey anmelden', $this->knopfText());
    }

    // AK2
    public function test_ak2_knopftext_ist_uebersetzt(): void
    {
        $de = $this->knopfText();
        app()->setLocale('en');
        $en = $this->knopfText();

        $this->assertNotSame('', $en);
        $this->assertNotSame($de, $en, 'Der Knopftext ist nicht ins Englische übersetzt.');
        $this->assertStringNotContainsString('userauth::', $en);
    }

    private function knopfText(): string
    {
        $seite = $this->seite();
        $this->assertSame(1, $this->knopf($seite), 'Der Passkey-Knopf fehlt auf der Login-Seite.');

        return trim($seite->query('//*[@data-testid="passkey-login"]')->item(0)->textContent);
    }

    /** @return array<string, array{0: string}> */
    public static function themes(): array
    {
        return ['tailwind' => ['tailwind'], 'bootstrap' => ['bootstrap']];
    }

    // AK2
    #[DataProvider('themes')]
    public function test_ak2_ohne_passwort_login_bleibt_nur_der_passkey_knopf_mit_oidc_und_social(string $theme): void
    {
        config()->set('userauth.theme', $theme);
        config()->set('userauth.social.enabled', true);
        config()->set('userauth.social.providers', ['google']);
        $this->app->instance(SocialIdentityProvider::class, new FakeSocialIdentityProvider);
        foreach (['enabled' => true, 'issuer' => 'https://auth.example.test', 'client_id' => 'c', 'client_secret' => 's'] as $schluessel => $wert) {
            config()->set("userauth.oidc.{$schluessel}", $wert);
        }

        $seite = $this->seite();
        $this->assertSame(1, $this->passwortFeld($seite), 'Aufbau: bei „immer“ steht das Passwortformular da.');

        config()->set('userauth.passwort_login', 'nie');
        $seite = $this->seite();

        $this->assertSame(0, $this->passwortFeld($seite), 'Bei „nie“ darf kein Passwortformular erscheinen.');
        $this->assertSame(1, $this->knopf($seite));
        $this->assertSame(1, $this->skript($seite));
        $this->assertSame(1, $this->anzahl($seite, '//a[@href="'.route('userauth.oidc.redirect').'"]'));
        $this->assertSame(1, $this->anzahl($seite, '//a[@href="'.route('userauth.social.redirect', 'google').'"]'));
    }

    // AK2
    public function test_ak2_skript_wird_ohne_session_und_cookies_als_javascript_ausgeliefert(): void
    {
        $antwort = $this->get(route('userauth.assets.passkey'))->assertOk();

        $this->assertStringContainsString('javascript', (string) $antwort->headers->get('Content-Type'));
        $this->assertSame([], $antwort->headers->getCookies(), 'Das Skript setzt Cookies.');
        $this->assertNotSame('', trim((string) ($antwort->streamedContent() ?: $antwort->getContent())));

        $route = Route::getRoutes()->getByName('userauth.assets.passkey');
        $this->assertSame([], array_intersect(['web', StartSession::class], $route->gatherMiddleware()), 'Die Skript-Route läuft mit Session.');
    }

    // AK2
    public function test_ak2_skript_ist_ohne_passkey_nicht_erreichbar(): void
    {
        $this->get(route('userauth.assets.passkey'))->assertOk(); // Gegenprobe: mit aktivem Passkey erreichbar

        config()->set('userauth.passkey.enabled', false);

        $this->get(route('userauth.assets.passkey'))->assertNotFound();
    }

    private function skriptText(): string
    {
        $antwort = $this->get(route('userauth.assets.passkey'))->assertOk();

        return (string) ($antwort->streamedContent() ?: $antwort->getContent());
    }

    // Randfall 1 angenähert: das Skript wird nur als Text geprüft, nicht in einem Browser ohne WebAuthn ausgeführt
    public function test_randfall1_angenaehert_skript_prueft_ob_der_browser_webauthn_kennt(): void
    {
        $this->assertMatchesRegularExpression('/window\.PublicKeyCredential/', $this->skriptText());
    }

    // Randfall 2 angenähert: das Skript wird nur als Text geprüft, nicht im Browser mit abgebrochenem Dialog ausgeführt
    public function test_randfall2_angenaehert_skript_behandelt_den_abbruch_des_dialogs(): void
    {
        $this->assertStringContainsString('NotAllowedError', $this->skriptText());
    }
}
