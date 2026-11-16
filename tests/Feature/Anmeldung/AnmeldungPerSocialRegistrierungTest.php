<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Anmeldung;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use ITHilbert\UserAuth\Tests\Feature\SocialLogin\SocialLoginTestCase;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\AnmeldungPerHilfen;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Social Login mit nicht eindeutiger E-Mail (AK14) und Selbstregistrierung nur im Modus `email` (AK15),
 * userauth-anmeldung-per (#1311). Aufbau: Social-Login-Testaufbau mit Fake-Anbieter.
 */
final class AnmeldungPerSocialRegistrierungTest extends SocialLoginTestCase
{
    use AnmeldungPerHilfen;

    private const GEMEINSAM = 'gem@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->grundeinstellung('benutzername');
        $this->benutzernameSpalteAnlegen();
        $this->emailIndexEntfernen();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function alleModi(): array
    {
        return ['email' => ['email'], 'benutzername' => ['benutzername'], 'beides' => ['beides']];
    }

    private function zaehlerKonten(): int
    {
        return User::withTrashed()->count();
    }

    // ------------------------------------------------------------------ AK14

    // AK14
    #[DataProvider('alleModi')]
    public function test_ak14_bei_mehreren_aktiven_konten_wird_nichts_verknuepft_und_nichts_angelegt(string $modus): void
    {
        $this->modus($modus);
        config()->set('userauth.social.allow_registration', true);
        $a = $this->makeUser(['email' => self::GEMEINSAM, 'benutzername' => 'a.eins']);
        $b = $this->makeUser(['email' => self::GEMEINSAM, 'benutzername' => 'b.zwei']);
        $this->provider->returnIdentity('google-sub-1', self::GEMEINSAM);
        $konten = $this->zaehlerKonten();

        $this->senden('get', route('userauth.social.callback', 'google'))
            ->assertRedirect(route('login'))->assertSessionHasErrors('social');
        $meldung = (string) session('errors')->first('social');

        $this->assertGuest();
        $this->assertNotSame('', $meldung);
        $this->assertSame($konten, $this->zaehlerKonten(), 'Es wurde ein Konto angelegt');
        $this->assertDatabaseCount('user_social_accounts', 0);
        $this->assertDatabaseCount('user_pending_social_links', 0);
        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        foreach (['a.eins', 'b.zwei', (string) $a->id.'|'.$b->id] as $geheim) {
            $this->assertStringNotContainsString($geheim, $meldung, 'Die Meldung verrät, welche Konten es gibt');
        }
        $this->assertStringContainsStringIgnoringCase('benutzername', $meldung, 'Hinweis auf den Benutzernamen');

        // die Meldung steht in der gerenderten Seite
        $this->senden('get', route('userauth.social.callback', 'google'));
        $this->assertStringContainsString(e($meldung), (string) $this->senden('get', route('login'))->assertOk()->getContent());
    }

    // AK14
    public function test_ak14_bei_genau_einem_aktiven_konto_und_bei_verknuepftem_anbieter_konto_bleibt_alles_wie_bisher(): void
    {
        $this->makeUser(['email' => self::GEMEINSAM, 'benutzername' => 'a.eins']);
        $b = $this->makeUser(['email' => self::GEMEINSAM, 'benutzername' => 'b.zwei']);
        $this->provider->returnIdentity('google-sub-1', self::GEMEINSAM);
        $this->senden('get', route('userauth.social.callback', 'google'))->assertSessionHasErrors('social');
        $this->assertGuest();

        // bereits verknüpftes Anbieter-Konto: meldet an, auch wenn die E-Mail mehrdeutig ist
        $this->linkGoogle($b, 'google-sub-1');
        $this->neueSitzung();
        $this->senden('get', route('userauth.social.callback', 'google'))->assertRedirect('/')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($b);

        // genau ein aktives Konto (das andere ist gelöscht): wird verknüpft und angemeldet
        $this->neueSitzung();
        $c = $this->makeUser(['email' => 'eins@example.test', 'benutzername' => 'c.drei']);
        $this->makeUser(['email' => 'eins@example.test', 'benutzername' => 'd.vier'])->delete();
        $this->provider->returnIdentity('google-sub-2', 'eins@example.test');
        $this->senden('get', route('userauth.social.callback', 'google'))->assertRedirect('/');
        $this->assertAuthenticatedAs($c);
        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $c->id, 'provider_user_id' => 'google-sub-2']);
    }

    // AK14
    #[DataProvider('alleModi')]
    public function test_ak14_ein_per_social_login_neu_angelegtes_konto_bekommt_keinen_benutzernamen(string $modus): void
    {
        $this->modus($modus);
        config()->set('userauth.social.allow_registration', true);
        $this->provider->returnIdentity('google-sub-9', 'neu@example.test');

        $this->senden('get', route('userauth.social.callback', 'google'))->assertRedirect('/');

        $this->assertAuthenticated();
        $zeile = DB::table('users')->where('email', 'neu@example.test')->first();
        $this->assertNotNull($zeile, 'Gegenprobe: das Konto wurde angelegt');
        $this->assertNull($zeile->benutzername);
    }

    // ------------------------------------------------------------------ AK15

    /**
     * @return array<string, array{string, string}>
     */
    public static function registrierungsRouten(): array
    {
        return [
            'GET register' => ['get', '/register'],
            'POST register' => ['post', '/register'],
            'GET Bestätigungshinweis' => ['get', '/register/bestaetigung'],
            'POST Link erneut anfordern' => ['post', '/register/bestaetigung'],
            'GET Bestätigungslink' => ['get', '/register/bestaetigen/1/abc'],
        ];
    }

    // AK15
    #[DataProvider('registrierungsRouten')]
    public function test_ak15_registrierungsrouten_antworten_in_den_modi_benutzername_und_beides_mit_404(string $methode, string $pfad): void
    {
        config()->set('userauth.registration', ['enabled' => true, 'role' => 'user', 'verify_ttl_minutes' => 60]);
        $daten = ['name' => 'Erika', 'email' => 'erika@example.test', 'password' => 'Sicheres-Passwort-2026!', 'password_confirmation' => 'Sicheres-Passwort-2026!'];

        // Gegenprobe: im Modus email ist die Route erreichbar (kein 404)
        $this->modus('email');
        $this->assertNotSame(404, $this->{$methode}($pfad, $methode === 'post' ? $daten : [])->getStatusCode(), 'Modus email: Route muss erreichbar sein');

        foreach (['benutzername', 'beides', ' BEIDES '] as $modus) {
            $this->modus($modus);
            $this->{$methode}($pfad, $methode === 'post' ? $daten : [])->assertNotFound();
        }
    }

    // AK15
    public function test_ak15_im_modus_email_gilt_das_bisherige_verhalten_der_registrierung(): void
    {
        config()->set('userauth.registration', ['enabled' => true, 'role' => 'user', 'verify_ttl_minutes' => 60]);
        $this->modus('email');

        $this->get('/register')->assertOk();
        $this->post('/register', ['name' => 'Erika', 'email' => 'erika@example.test', 'password' => 'Sicheres-Passwort-2026!', 'password_confirmation' => 'Sicheres-Passwort-2026!'])->assertRedirect();

        $this->assertSame(1, User::where('email', 'erika@example.test')->count());
    }
}
