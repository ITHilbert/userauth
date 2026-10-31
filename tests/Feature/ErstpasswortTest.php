<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Application\Passwort\PasswortSetzen;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Fixtures\UserMitHashCast;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Erstpasswort ohne Historien-Eintrag, auch bei `hashed`-Cast (userauth-erstpasswort, Dashboard #335).
 */
final class ErstpasswortTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    private const ERSTPASSWORT = 'Erstpasswort-2026!';

    private const ALTES_PASSWORT = 'Altes-Passwort-1!';

    private const NEUES_PASSWORT = 'Neues-Passwort-2027!';

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        config()->set('userauth.password_policy.enabled', true);
        config()->set('userauth.password_policy.prevent_reuse_last_passwords', 3);

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => self::ROLLE_NORMAL, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
        foreach (['user_create', 'user_edit'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            DB::table('role_permission')->insert(['role_id' => self::ROLLE_NORMAL, 'permission_id' => $i + 1]);
        }
    }

    // ---------------------------------------------------------------- Hilfen

    /**
     * @return array<string, array{0: class-string<User>}>
     */
    public static function modelle(): array
    {
        return ['ohne Cast' => [User::class], 'mit hashed-Cast' => [UserMitHashCast::class]];
    }

    /**
     * @param  class-string<User>  $modell
     */
    private function modellSetzen(string $modell): void
    {
        config()->set('auth.providers.users.model', $modell);
    }

    /**
     * @param  class-string<User>  $modell
     */
    private function admin(string $modell): User
    {
        return $modell::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'admin@example.test']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formularDaten(): array
    {
        return [
            'name' => 'Neuer Nutzer',
            'email' => 'neu@example.test',
            'password' => self::ERSTPASSWORT,
            'password2' => self::ERSTPASSWORT,
            'role_id' => self::ROLLE_NORMAL,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function anlegen(User $admin): array
    {
        $this->actingAs($admin)->post('/users/store', $this->formularDaten())->assertSessionHasNoErrors();

        return (array) DB::table('users')->where('email', 'neu@example.test')->first();
    }

    private function historienAnzahl(int|string $nutzerId): int
    {
        return DB::table('user_password_histories')->where('user_id', $nutzerId)->count();
    }

    private function hashVonLeerInHistorie(): bool
    {
        foreach (DB::table('user_password_histories')->pluck('password') as $hash) {
            if (Hash::check('', (string) $hash)) {
                return true;
            }
        }

        return false;
    }

    private function bestehenderNutzer(string $modell): User
    {
        return $modell::factory()->create([
            'role_id' => self::ROLLE_NORMAL,
            'email' => 'alt@example.test',
            'password' => Hash::make(self::ALTES_PASSWORT),
        ]);
    }

    // ---------------------------------------------------------------- AK1

    // AK1
    public function test_ak1_hashed_cast_hasht_den_leeren_platzhalter_aufbau_greift(): void
    {
        $nutzer = new UserMitHashCast;
        $nutzer->password = '';

        $this->assertNotSame('', $nutzer->getAttributes()['password'], 'Aufbau: der Cast hasht den Platzhalter');
        $this->assertTrue(Hash::check('', $nutzer->getAttributes()['password']));
    }

    // AK1
    public function test_ak1_anlegen_mit_hashed_cast_erzeugt_keinen_historieneintrag(): void
    {
        $this->modellSetzen(UserMitHashCast::class);
        $admin = $this->admin(UserMitHashCast::class);

        $zeile = $this->anlegen($admin);

        $this->assertNotEmpty($zeile, 'Gegenprobe: Benutzer wurde angelegt');
        $this->assertSame(0, $this->historienAnzahl($zeile['id']));
        $this->assertFalse($this->hashVonLeerInHistorie(), 'Hash von "" darf nicht in der Historie stehen');
    }

    // AK1
    public function test_ak1_anlegen_mit_hashed_cast_speichert_passwort_gehasht_passend_zum_gesendeten(): void
    {
        $this->modellSetzen(UserMitHashCast::class);

        $zeile = $this->anlegen($this->admin(UserMitHashCast::class));

        $this->assertNotSame(self::ERSTPASSWORT, $zeile['password']);
        $this->assertTrue(Hash::check(self::ERSTPASSWORT, $zeile['password']));
        $this->assertFalse(Hash::check('', $zeile['password']));
    }

    // AK1
    public function test_ak1_anlegen_mit_hashed_cast_setzt_password_changed_at(): void
    {
        $this->modellSetzen(UserMitHashCast::class);
        $this->travelTo(now()->startOfSecond());

        $zeile = $this->anlegen($this->admin(UserMitHashCast::class));

        $this->assertNotNull($zeile['password_changed_at']);
        $this->assertEqualsWithDelta(now()->getTimestamp(), strtotime((string) $zeile['password_changed_at']), 5);
    }

    // ---------------------------------------------------------------- AK2

    // AK2
    public function test_ak2_anlegen_ohne_cast_erzeugt_keinen_historieneintrag(): void
    {
        $this->modellSetzen(User::class);

        $zeile = $this->anlegen($this->admin(User::class));

        $this->assertNotEmpty($zeile, 'Gegenprobe: Benutzer wurde angelegt');
        $this->assertSame(0, $this->historienAnzahl($zeile['id']));
        $this->assertFalse($this->hashVonLeerInHistorie());
    }

    // AK2
    public function test_ak2_anlegen_ohne_cast_speichert_passwort_gehasht_passend_zum_gesendeten(): void
    {
        $this->modellSetzen(User::class);

        $zeile = $this->anlegen($this->admin(User::class));

        $this->assertNotSame(self::ERSTPASSWORT, $zeile['password']);
        $this->assertTrue(Hash::check(self::ERSTPASSWORT, $zeile['password']));
    }

    // AK2
    public function test_ak2_anlegen_ohne_cast_setzt_password_changed_at(): void
    {
        $this->modellSetzen(User::class);
        $this->travelTo(now()->startOfSecond());

        $zeile = $this->anlegen($this->admin(User::class));

        $this->assertNotNull($zeile['password_changed_at']);
        $this->assertEqualsWithDelta(now()->getTimestamp(), strtotime((string) $zeile['password_changed_at']), 5);
    }

    // ---------------------------------------------------------------- AK3

    // AK3
    #[DataProvider('modelle')]
    public function test_ak3_passwort_aendern_schreibt_alten_hash_in_die_historie(string $modell): void
    {
        $this->modellSetzen($modell);
        $nutzer = $this->bestehenderNutzer($modell);
        $alterHash = (string) DB::table('users')->where('id', $nutzer->id)->value('password');
        $this->assertSame(0, $this->historienAnzahl($nutzer->id), 'Ausgangslage: keine Historie');

        $this->actingAs($nutzer)->from(route('password.edit'))
            ->post(route('password.update'), ['password' => self::NEUES_PASSWORT, 'password_confirmation' => self::NEUES_PASSWORT])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, (string) DB::table('users')->where('id', $nutzer->id)->value('password')), 'Gegenprobe: Passwort geändert');
        $this->assertSame(1, $this->historienAnzahl($nutzer->id));
        $this->assertDatabaseHas('user_password_histories', ['user_id' => $nutzer->id, 'password' => $alterHash]);
    }

    // AK3
    #[DataProvider('modelle')]
    public function test_ak3_admin_aendert_passwort_und_alter_hash_kommt_in_die_historie(string $modell): void
    {
        $this->modellSetzen($modell);
        $ziel = $this->bestehenderNutzer($modell);
        $alterHash = (string) DB::table('users')->where('id', $ziel->id)->value('password');
        $this->assertSame(0, $this->historienAnzahl($ziel->id), 'Ausgangslage: keine Historie');

        $this->actingAs($this->admin($modell))->post('/users/update/'.$ziel->id, [
            'name' => 'Neuer Name', 'email' => 'neu-alt@example.test',
            'password' => self::NEUES_PASSWORT, 'password2' => self::NEUES_PASSWORT,
            'role_id' => self::ROLLE_NORMAL,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, (string) DB::table('users')->where('id', $ziel->id)->value('password')), 'Gegenprobe: Passwort geändert');
        $this->assertSame(1, $this->historienAnzahl($ziel->id));
        $this->assertDatabaseHas('user_password_histories', ['user_id' => $ziel->id, 'password' => $alterHash]);
    }

    // AK3
    public function test_ak3_neu_angelegter_nutzer_bekommt_beim_ersten_aendern_genau_den_erstpasswort_hash(): void
    {
        $this->modellSetzen(UserMitHashCast::class);
        $admin = $this->admin(UserMitHashCast::class);
        $zeile = $this->anlegen($admin);
        $this->assertSame(0, $this->historienAnzahl($zeile['id']), 'Gegenprobe: nach dem Anlegen leer');

        $this->actingAs($admin)->post('/users/update/'.$zeile['id'], [
            'name' => 'Neuer Nutzer', 'email' => 'neu@example.test',
            'password' => self::NEUES_PASSWORT, 'password2' => self::NEUES_PASSWORT,
            'role_id' => self::ROLLE_NORMAL,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->historienAnzahl($zeile['id']));
        $this->assertDatabaseHas('user_password_histories', ['user_id' => $zeile['id'], 'password' => $zeile['password']]);
        $this->assertFalse($this->hashVonLeerInHistorie());
    }

    // ---------------------------------------------------------------- Randfall

    // Randfall 1
    #[DataProvider('modelle')]
    public function test_randfall1_gehashter_leerer_platzhalter_gelangt_ueber_passwort_setzen_nie_in_die_historie(string $modell): void
    {
        $this->modellSetzen($modell);
        $nutzer = $this->bestehenderNutzer($modell);
        DB::table('users')->where('id', $nutzer->id)->update(['password' => Hash::make('')]);
        $nutzer = $nutzer->fresh();
        $this->assertTrue(Hash::check('', (string) $nutzer->getAuthPassword()), 'Aufbau: Platzhalter ist der Hash von ""');

        app(PasswortSetzen::class)->handle($nutzer, self::NEUES_PASSWORT);

        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, (string) DB::table('users')->where('id', $nutzer->id)->value('password')), 'Gegenprobe: Passwort gesetzt');
        $this->assertSame(0, $this->historienAnzahl($nutzer->id));
        $this->assertFalse($this->hashVonLeerInHistorie());
    }

    // Randfall 1
    #[DataProvider('modelle')]
    public function test_randfall1_ungehashter_leerer_platzhalter_gelangt_ueber_passwort_setzen_nie_in_die_historie(string $modell): void
    {
        $this->modellSetzen($modell);
        $nutzer = $this->bestehenderNutzer($modell);
        DB::table('users')->where('id', $nutzer->id)->update(['password' => '']);
        $nutzer = $nutzer->fresh();

        app(PasswortSetzen::class)->handle($nutzer, self::NEUES_PASSWORT);

        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, (string) DB::table('users')->where('id', $nutzer->id)->value('password')), 'Gegenprobe: Passwort gesetzt');
        $this->assertSame(0, $this->historienAnzahl($nutzer->id));
    }
}
