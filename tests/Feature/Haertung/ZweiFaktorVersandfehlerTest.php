<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Haertung;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Feature\SocialLogin\SocialLoginTestCase;
use ITHilbert\UserAuth\Tests\Support\NachrichtenAbfangen;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Zwei-Faktor-Code lässt sich nicht versenden (Mailserver nicht erreichbar). Abgedeckt: AK14b (userauth-haertung).
 *
 * Der Versandfehler kommt aus dem Mail-System: ein Transport, der beim Senden eine TransportException wirft.
 * Erwarteter Sprachschlüssel für den Hinweis: `userauth::zwei_faktor.versand_fehlgeschlagen`
 * (de: "Der Code konnte nicht versendet werden. Bitte versuchen Sie es später erneut.").
 */
final class ZweiFaktorVersandfehlerTest extends SocialLoginTestCase
{
    use NachrichtenAbfangen;

    private const EMAIL = 'max@firma.de';

    private const HINWEIS = 'Der Code konnte nicht versendet werden. Bitte versuchen Sie es später erneut.';

    /** @var array<string, string> */
    private array $sitzungen = [];

    private string $aktiv = 'a';

    private Model $nutzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nachrichtenAbfangen();
        View::addLocation(__DIR__.'/../../Fixtures/views');
        app()->setLocale('de');
        config()->set('userauth.two_factor_enabled', true);
        config()->set('userauth.audit_log_enabled', false);
        config()->set('userauth.password_policy.enabled', false);

        $this->nutzer = $this->makeUser(['email' => self::EMAIL]);
        $this->linkGoogle($this->nutzer, 'google-sub-1');
        $this->provider->returnIdentity('google-sub-1', self::EMAIL);

        $this->browser('a');
    }

    // ---------------------------------------------------------------- Hilfen

    private function browser(string $name): void
    {
        $this->aktiv = $name;
        $this->sitzungen[$name] ??= Str::random(40);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withCookie((string) config('session.cookie'), $this->sitzungen[$name]);
    }

    /**
     * @param  array<string, mixed>  $daten
     */
    private function senden(string $methode, string $uri, array $daten = []): TestResponse
    {
        $this->app['cookie']->flushQueuedCookies();
        $antwort = $this->{$methode}($uri, $daten);

        $this->sitzungen[$this->aktiv] = session()->getId();
        $this->withCookie((string) config('session.cookie'), $this->sitzungen[$this->aktiv]);

        return $antwort;
    }

    /**
     * Ab jetzt wirft jeder Mailversand eine TransportException (Mailserver nicht erreichbar).
     */
    private function mailserverNichtErreichbar(): void
    {
        $manager = new MailManager($this->app);
        $manager->extend('nicht-erreichbar', fn (): AbstractTransport => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection refused');
            }

            public function __toString(): string
            {
                return 'nicht-erreichbar://';
            }
        });
        config()->set('mail.mailers.nicht-erreichbar', ['transport' => 'nicht-erreichbar']);
        config()->set('mail.default', 'nicht-erreichbar');

        Mail::swap($manager);
        $this->controllerNeuAufloesen();
    }

    private function mailserverErreichbar(): void
    {
        config()->set('mail.default', 'array');
        Mail::swap($this->app->make(MailManager::class));
        $this->nachrichtenAbfangen();
        $this->controllerNeuAufloesen();
    }

    /**
     * Der Router hält die Controller-Instanz je Route (samt injiziertem Mailer); nach dem Wechsel des Mailers neu auflösen.
     */
    private function controllerNeuAufloesen(): void
    {
        foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
            $route->flushController();
        }
    }

    private function passwortAnmelden(): TestResponse
    {
        return $this->senden('post', route('login'), ['email' => self::EMAIL, 'password' => 'password']);
    }

    private function sozialAnmelden(): TestResponse
    {
        return $this->senden('get', route('userauth.social.callback', 'google'));
    }

    private function codeErneutSenden(): TestResponse
    {
        return $this->senden('post', route('verify.resend'));
    }

    private function codeEingeben(string $code): TestResponse
    {
        $this->from(route('verify.index'));

        return $this->senden('post', route('verify.store'), ['two_factor_code' => $code]);
    }

    private function assertKeinServerfehler(TestResponse $antwort): void
    {
        $this->assertLessThan(500, $antwort->getStatusCode(), 'Ein Versandfehler darf keinen Serverfehler auslösen.');
    }

    /**
     * Dem Redirect folgen und den Hinweis in der gerenderten Seite finden.
     */
    private function assertHinweisInSeite(TestResponse $antwort): void
    {
        $this->assertKeinServerfehler($antwort);
        $this->assertTrue($antwort->isRedirection(), 'Erwartet wird eine Weiterleitung mit Hinweis, Status '.$antwort->getStatusCode());

        $this->senden('get', (string) $antwort->headers->get('Location'))->assertOk()->assertSee(self::HINWEIS);
    }

    private function assertNichtAngemeldet(): void
    {
        $this->assertGuest();

        $seite = $this->senden('get', route('password.edit'));
        $this->assertContains($seite->getStatusCode(), [301, 302, 303, 307, 401, 403], 'Seite hinter auth darf nicht erreichbar sein, Status '.$seite->getStatusCode());
        $this->assertGuest();
    }

    /**
     * Gegenprobe: Mit erreichbarem Mailserver läuft derselbe Ablauf durch (Mail geht raus, der Code meldet an).
     *
     * @param  callable(): TestResponse  $anmelden
     */
    private function gegenprobeMitFunktionierendemMailer(callable $anmelden): void
    {
        $this->mailserverErreichbar();
        $this->sitzungen['gegenprobe'] = Str::random(40);
        $this->browser('gegenprobe');

        $anmelden()->assertRedirect(route('verify.index'));
        $this->assertCount(1, $this->nachrichtenAn(self::EMAIL), 'Gegenprobe: Mit erreichbarem Mailserver wird der Code versendet.');

        $this->codeEingeben($this->zweiFaktorCode(self::EMAIL));
        $this->assertAuthenticatedAs($this->nutzer);
    }

    /**
     * Passwort-Anmeldung mit funktionierendem Mailer (Code offen), danach ist der Mailserver nicht mehr erreichbar.
     */
    private function offeneZweiFaktorDanachMailserverAus(): void
    {
        $this->passwortAnmelden()->assertRedirect(route('verify.index'));
        $this->assertCount(1, $this->nachrichtenAn(self::EMAIL));

        $this->travel(61)->seconds();
        $this->mailserverNichtErreichbar();
    }

    // ------------------------------------------------------- Weg 1: Passwort-Login

    // AK14b
    public function test_passwort_login_ohne_erreichbaren_mailserver_liefert_keinen_serverfehler(): void
    {
        $this->mailserverNichtErreichbar();

        $antwort = $this->passwortAnmelden();

        $this->assertKeinServerfehler($antwort);
    }

    // AK14b
    public function test_passwort_login_ohne_erreichbaren_mailserver_zeigt_den_hinweis_in_der_seite(): void
    {
        $this->mailserverNichtErreichbar();

        $this->assertHinweisInSeite($this->passwortAnmelden());
    }

    // AK14b
    public function test_passwort_login_ohne_erreichbaren_mailserver_bleibt_unangemeldet(): void
    {
        $this->mailserverNichtErreichbar();

        $this->assertKeinServerfehler($this->passwortAnmelden());

        $this->assertNichtAngemeldet();

        $this->gegenprobeMitFunktionierendemMailer(fn (): TestResponse => $this->passwortAnmelden());
    }

    // ------------------------------------------------------- Weg 2: Social Login

    // AK14b
    public function test_social_login_ohne_erreichbaren_mailserver_liefert_keinen_serverfehler(): void
    {
        $this->mailserverNichtErreichbar();

        $antwort = $this->sozialAnmelden();

        $this->assertKeinServerfehler($antwort);
    }

    // AK14b
    public function test_social_login_ohne_erreichbaren_mailserver_zeigt_den_hinweis_in_der_seite(): void
    {
        $this->mailserverNichtErreichbar();

        $this->assertHinweisInSeite($this->sozialAnmelden());
    }

    // AK14b
    public function test_social_login_ohne_erreichbaren_mailserver_bleibt_unangemeldet(): void
    {
        $this->mailserverNichtErreichbar();

        $this->assertKeinServerfehler($this->sozialAnmelden());

        $this->assertNichtAngemeldet();

        $this->gegenprobeMitFunktionierendemMailer(fn (): TestResponse => $this->sozialAnmelden());
    }

    // ------------------------------------------------------- Weg 3: Code erneut senden

    // AK14b
    public function test_code_erneut_senden_ohne_erreichbaren_mailserver_liefert_keinen_serverfehler(): void
    {
        $this->offeneZweiFaktorDanachMailserverAus();

        $antwort = $this->codeErneutSenden();

        $this->assertKeinServerfehler($antwort);
    }

    // AK14b
    public function test_code_erneut_senden_ohne_erreichbaren_mailserver_zeigt_den_hinweis_in_der_seite(): void
    {
        $this->offeneZweiFaktorDanachMailserverAus();

        $this->assertHinweisInSeite($this->codeErneutSenden());
    }

    // AK14b
    public function test_code_erneut_senden_ohne_erreichbaren_mailserver_meldet_nicht_an(): void
    {
        $this->offeneZweiFaktorDanachMailserverAus();

        $this->assertKeinServerfehler($this->codeErneutSenden());

        $this->assertNichtAngemeldet();

        // Gegenprobe: derselbe Ablauf mit erreichbarem Mailserver versendet den neuen Code, der anmeldet
        $this->mailserverErreichbar();
        $this->sitzungen['gegenprobe'] = Str::random(40);
        $this->browser('gegenprobe');
        $this->passwortAnmelden()->assertRedirect(route('verify.index'));
        $this->travel(61)->seconds();
        $this->codeErneutSenden();

        $this->assertCount(2, $this->nachrichtenAn(self::EMAIL), 'Gegenprobe: Mit erreichbarem Mailserver wird der Code erneut versendet.');
        $this->codeEingeben($this->zweiFaktorCode(self::EMAIL));
        $this->assertAuthenticatedAs($this->nutzer);
    }
}
