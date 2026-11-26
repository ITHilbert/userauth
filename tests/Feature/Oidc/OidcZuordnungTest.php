<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Oidc;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Application\Oidc\Contracts\OidcIdentityProvider;
use ITHilbert\UserAuth\Application\SocialLogin\Contracts\SocialIdentityProvider;
use ITHilbert\UserAuth\Application\SocialLogin\ListAvailableSocialProviders;
use ITHilbert\UserAuth\Mail\SocialLinkConfirmation;
use ITHilbert\UserAuth\Tests\Fakes\FakeOidcIdentityProvider;
use ITHilbert\UserAuth\Tests\Fakes\FakeSocialIdentityProvider;
use ITHilbert\UserAuth\Tests\Support\OidcTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Zuordnung der OIDC-Identität zu einem Benutzer, Rollen bei Neuanlage (AK9 bis AK12, AK20, Randfall 4).
 * Der Port zum Anbieter ist ein Fake; der Weg läuft über die HTTP-Routen.
 */
final class OidcZuordnungTest extends TestCase
{
    use OidcTestHilfe;
    use RefreshDatabase;

    private FakeOidcIdentityProvider $anbieter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oidcKonfigurieren();
        $this->anbieter = new FakeOidcIdentityProvider;
        $this->app->instance(OidcIdentityProvider::class, $this->anbieter);
        $this->oidcSitzungStarten();
    }

    /**
     * @param  list<string>  $groups
     */
    private function anmelden(string $sub, string $email, ?string $name = 'Max Muster', array $groups = []): TestResponse
    {
        $this->anbieter->returnIdentity($sub, $email, $name, $groups);
        [$state] = $this->oidcStart();

        return $this->oidcCallback(['code' => 'CODE-1', 'state' => $state]);
    }

    private function verknuepfungen(): int
    {
        return DB::table('user_social_accounts')->where('provider', 'oidc')->count();
    }

    // AK9
    public function test_ak9_verknuepfter_aktiver_benutzer_wird_unabhaengig_von_der_email_angemeldet(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->verknuepfeOidc($user, 'sub-1');

        $this->anmelden('sub-1', 'ganz-andere@anbieter.de', groups: ['admins'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, $this->verknuepfungen());
    }

    // AK9
    public function test_ak9_deaktivierter_verknuepfter_benutzer_wird_nicht_angemeldet(): void
    {
        config()->set('userauth.oidc.auto_create', true);
        $geloescht = $this->makeUser(['email' => 'weg@firma.de']);
        $this->verknuepfeOidc($geloescht, 'sub-weg');
        $geloescht->delete();
        $aktiv = $this->makeUser(['email' => 'aktiv@firma.de']);
        $this->verknuepfeOidc($aktiv, 'sub-aktiv');

        $this->assertAbgelehnt($this->anmelden('sub-weg', 'weg@firma.de'));
        $this->assertSame(2, $this->userModel()::withTrashed()->count(), 'Für das gelöschte Konto wurde ein neues angelegt.');

        // Gegenprobe: der aktive verknüpfte Benutzer kommt über denselben Weg hinein
        $this->anmelden('sub-aktiv', 'aktiv@firma.de')->assertRedirect('/');
        $this->assertAuthenticatedAs($aktiv);
    }

    // AK10
    public function test_ak10_genau_ein_aktiver_benutzer_mit_email_wird_verknuepft_und_ohne_bestaetigungsmail_angemeldet(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de'], verified: false);

        $this->anmelden('sub-neu', 'Max@Firma.DE')->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $user->getKey(),
            'provider' => 'oidc',
            'provider_user_id' => 'sub-neu',
        ]);
        Mail::assertNotQueued(SocialLinkConfirmation::class);
        Mail::assertNotSent(SocialLinkConfirmation::class);
        $this->assertSame(0, DB::table('user_pending_social_links')->count());
    }

    // AK10
    public function test_ak10_mehrere_aktive_benutzer_mit_derselben_email_werden_nicht_verknuepft(): void
    {
        DB::statement('DROP INDEX users_email_unique');
        $erster = $this->makeUser(['email' => 'gemeinsam@firma.de']);
        $zweiter = $this->makeUser(['email' => 'Gemeinsam@Firma.de']);

        $this->assertAbgelehnt($this->anmelden('sub-1', 'gemeinsam@firma.de'));
        $this->assertSame(0, $this->verknuepfungen());

        // Gegenprobe: ist nur noch einer aktiv, läuft derselbe Weg
        $zweiter->delete();
        $this->anmelden('sub-1', 'gemeinsam@firma.de')->assertRedirect('/');
        $this->assertAuthenticatedAs($erster);
        $this->assertSame(1, $this->verknuepfungen());
    }

    // AK10
    public function test_ak10_benutzer_mit_anderer_oidc_verknuepfung_wird_nicht_angemeldet(): void
    {
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->verknuepfeOidc($user, 'sub-alt');

        $this->assertAbgelehnt($this->anmelden('sub-neu', 'max@firma.de'));
        $this->assertSame(1, $this->verknuepfungen());
        $this->assertDatabaseMissing('user_social_accounts', ['provider_user_id' => 'sub-neu']);

        // Gegenprobe: mit dem verknüpften sub geht es
        $this->anmelden('sub-alt', 'max@firma.de')->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    // AK11
    public function test_ak11_ohne_auto_create_gibt_es_kein_konto_mit_auto_create_wird_eines_angelegt(): void
    {
        $this->assertAbgelehnt($this->anmelden('sub-neu', 'neu@firma.de', 'Neue Person'));
        $this->assertSame(0, $this->userModel()::withTrashed()->count());
        $this->assertSame(0, $this->verknuepfungen());
        $this->assertNotEmpty($this->fehlermeldungen());

        // Gegenprobe: mit auto_create dieselbe Identität
        config()->set('userauth.oidc.auto_create', true);
        $this->anmelden('sub-neu', 'neu@firma.de', 'Neue Person')->assertRedirect('/');

        $neu = $this->userModel()::where('email', 'neu@firma.de')->firstOrFail();
        $this->assertSame('Neue Person', $neu->name);
        $this->assertNotNull($neu->email_verified_at, 'Der angelegte Benutzer ist nicht bestätigt.');
        $this->assertAuthenticatedAs($neu);
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $neu->getKey(),
            'provider' => 'oidc',
            'provider_user_id' => 'sub-neu',
        ]);
    }

    // AK11
    public function test_ak11_email_eines_geloeschten_benutzers_legt_nichts_an(): void
    {
        config()->set('userauth.oidc.auto_create', true);
        $geloescht = $this->makeUser(['email' => 'weg@firma.de']);
        $geloescht->delete();

        $this->assertAbgelehnt($this->anmelden('sub-1', 'weg@firma.de'));

        $this->assertSame(1, $this->userModel()::withTrashed()->where('email', 'weg@firma.de')->count());
        $this->assertSame(0, $this->verknuepfungen());

        // Gegenprobe: eine andere, unbekannte E-Mail wird mit derselben Einstellung angelegt
        $this->anmelden('sub-2', 'frisch@firma.de')->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => 'frisch@firma.de']);
    }

    // AK20
    public function test_ak20_offenes_selbstregistriertes_konto_wird_bestaetigt_und_verliert_passwort_und_remember_token(): void
    {
        $user = $this->makeUser(['email' => 'offen@firma.de', 'self_registered' => true, 'remember_token' => 'altes-token'], verified: false);
        $alterHash = (string) $user->fresh()->password;

        // Gegenprobe: vorher gilt das lokale Passwort, E-Mail unbestätigt, Token gesetzt
        $this->assertTrue(Hash::check('password', $alterHash));
        $this->assertNull($user->fresh()->email_verified_at);

        $this->anmelden('sub-offen', 'offen@firma.de')->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $user->getKey(), 'provider_user_id' => 'sub-offen']);
        $neu = $user->fresh();
        $this->assertNotNull($neu->email_verified_at, 'Die E-Mail gilt nicht als bestätigt.');
        $this->assertFalse(Hash::check('password', (string) $neu->password), 'Das alte Passwort ist noch gültig.');
        $this->assertNotSame($alterHash, (string) $neu->password);
        $this->assertNotSame('altes-token', $neu->remember_token, 'Das remember_token wurde nicht widerrufen.');
    }

    // AK20
    public function test_ak20_bestaetigtes_konto_behaelt_passwort_und_remember_token(): void
    {
        $user = $this->makeUser(['email' => 'fertig@firma.de', 'self_registered' => true, 'remember_token' => 'bleibt-token']);
        $alterHash = (string) $user->fresh()->password;

        $this->anmelden('sub-fertig', 'fertig@firma.de')->assertRedirect('/');

        // Gegenprobe: die Verknüpfung entstand, der Ablauf lief also durch
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $user->getKey(), 'provider_user_id' => 'sub-fertig']);
        $neu = $user->fresh();
        $this->assertSame($alterHash, (string) $neu->password);
        $this->assertTrue(Hash::check('password', (string) $neu->password));
        $this->assertSame('bleibt-token', $neu->remember_token);
    }

    /**
     * @return array<string, array{0: string, 1: list<string>, 2: int, 3: string}>
     */
    public static function rollenZuordnung(): array
    {
        // Rollen im Test: dev = 1, admin = 2, user = 3
        return [
            'passende Gruppe' => ['admins=admin', ['admins'], 2, 'user'],
            'erste Zuordnung in Reihenfolge der Config' => ['admins=admin,devs=dev', ['devs', 'admins'], 2, 'user'],
            'Reihenfolge der Config entscheidet, nicht die der Gruppen' => ['devs=dev,admins=admin', ['admins', 'devs'], 1, 'user'],
            'keine passende Gruppe' => ['admins=admin', ['andere'], 3, 'user'],
            'keine Gruppen' => ['admins=admin', [], 3, 'user'],
            'Rolle existiert nicht' => ['ghosts=gibtsnicht', ['ghosts'], 3, 'user'],
            'default_role aus der Config' => ['admins=admin', [], 1, 'dev'],
        ];
    }

    /**
     * @param  list<string>  $groups
     */
    // AK12
    #[DataProvider('rollenZuordnung')]
    public function test_ak12_rolle_bei_neuanlage_aus_group_roles_sonst_default_role(string $groupRoles, array $groups, int $erwarteteRolle, string $defaultRole): void
    {
        config()->set('userauth.oidc.auto_create', true);
        config()->set('userauth.oidc.group_roles', $groupRoles);
        config()->set('userauth.oidc.default_role', $defaultRole);

        $this->anmelden('sub-neu', 'neu@firma.de', groups: $groups)->assertRedirect('/');

        $this->assertSame($erwarteteRolle, (int) $this->userModel()::where('email', 'neu@firma.de')->value('role_id'));
    }

    // AK12
    public function test_ak12_rolle_eines_verknuepften_benutzers_bleibt_unveraendert(): void
    {
        config()->set('userauth.oidc.group_roles', 'admins=admin');
        $user = $this->makeUser(['email' => 'eins@firma.de']);
        $this->verknuepfeOidc($user, 'sub-eins');

        $this->anmelden('sub-eins', 'eins@firma.de', groups: ['admins'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(3, (int) $user->fresh()->role_id);
    }

    // AK12
    public function test_ak12_rolle_eines_per_email_verknuepften_benutzers_bleibt_unveraendert(): void
    {
        config()->set('userauth.oidc.group_roles', 'admins=admin');
        $user = $this->makeUser(['email' => 'zwei@firma.de']);

        $this->anmelden('sub-zwei', 'zwei@firma.de', groups: ['admins'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $user->getKey(), 'provider_user_id' => 'sub-zwei']);
        $this->assertSame(3, (int) $user->fresh()->role_id);
    }

    // Randfall 4
    public function test_randfall4_oidc_verknuepfung_ist_kein_social_anbieter(): void
    {
        config()->set('userauth.social.enabled', true);
        config()->set('userauth.social.providers', ['google']);
        $this->app->instance(SocialIdentityProvider::class, new FakeSocialIdentityProvider);
        $user = $this->makeUser(['email' => 'max@firma.de']);
        $this->verknuepfeOidc($user, 'sub-1');

        $this->assertSame(['google'], app(ListAvailableSocialProviders::class)->handle());

        $this->actingAs($user)
            ->get(route('userauth.social.accounts'))
            ->assertOk()
            ->assertSee(route('userauth.social.redirect', 'google'), false)
            ->assertDontSee('/auth/oidc/', false);

        $this->actingAs($user)->delete('/social-accounts/oidc')->assertNotFound();
        $this->assertSame(1, $this->verknuepfungen());
    }
}
