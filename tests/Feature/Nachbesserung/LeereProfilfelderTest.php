<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Nachbesserung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Leerer Stundensatz und Geburtstag beim Speichern. Abgedeckt: AK1, AK2, Randfall 3, Randfall 4 (userauth-nachbesserung).
 *
 * SQLite akzeptiert '' in decimal/date-Spalten (MariaDB nicht) – darum wird der gespeicherte Wert exakt geprüft.
 */
final class LeereProfilfelderTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../../Fixtures/views');

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

    /**
     * Leer (''), explizit null und fehlend.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function leereWerteFuerStundensatz(): array
    {
        return [
            'leer' => [['hourly_rate' => '']],
            'null' => [['hourly_rate' => null]],
            'fehlt' => [[]],
        ];
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function leereWerteFuerGeburtstag(): array
    {
        return [
            'leer' => [['birthday' => '']],
            'null' => [['birthday' => null]],
            'fehlt' => [[]],
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_NORMAL]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formularDaten(array $extra = []): array
    {
        return array_merge([
            'name' => 'Neuer Nutzer',
            'email' => 'neu@example.test',
            'password' => 'Ein-Passwort-456!',
            'password2' => 'Ein-Passwort-456!',
            'role_id' => self::ROLLE_NORMAL,
        ], $extra);
    }

    /**
     * Bestehender Nutzer mit gefüllten Werten, damit ein "leer" sichtbar etwas überschreibt.
     */
    private function bestehenderNutzer(): User
    {
        $user = User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'alt@example.test']);
        DB::table('users')->where('id', $user->id)->update(['hourly_rate' => 45.5, 'birthday' => '1990-05-17']);

        return $user;
    }

    private function gespeicherterStundensatz(string $email): float
    {
        // SQLite speichert '' in einer decimal-Spalte als Text; MariaDB würde es ablehnen. Nur echte Zahlen gelten.
        $typ = DB::table('users')->where('email', $email)->selectRaw('typeof(hourly_rate) as typ')->value('typ');
        $this->assertContains($typ, ['integer', 'real'], 'hourly_rate muss als Zahl gespeichert sein, nicht als Text ('.$typ.')');

        return (float) DB::table('users')->where('email', $email)->value('hourly_rate');
    }

    // ---------------------------------------------------------------- AK1

    // AK1
    #[DataProvider('leereWerteFuerStundensatz')]
    public function test_ak1_store_mit_leerem_oder_fehlendem_stundensatz_speichert_null_euro(array $feld): void
    {
        $this->actingAs($this->admin())
            ->post('/users/store', $this->formularDaten($feld))
            ->assertRedirect(route('user'));

        $this->assertDatabaseHas('users', ['email' => 'neu@example.test']);
        $this->assertSame(0.0, $this->gespeicherterStundensatz('neu@example.test'));
    }

    // AK1
    #[DataProvider('leereWerteFuerStundensatz')]
    public function test_ak1_update_mit_leerem_oder_fehlendem_stundensatz_setzt_null_euro(array $feld): void
    {
        $ziel = $this->bestehenderNutzer();
        $this->assertSame(45.5, $this->gespeicherterStundensatz('alt@example.test'), 'Vorbedingung: Stundensatz ist gesetzt');

        $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->formularDaten(['email' => 'alt@example.test', 'password' => '', 'password2' => ''] + $feld))
            ->assertRedirect();

        $this->assertSame(0.0, $this->gespeicherterStundensatz('alt@example.test'));
    }

    // ---------------------------------------------------------------- AK2

    // AK2
    #[DataProvider('leereWerteFuerGeburtstag')]
    public function test_ak2_store_ohne_geburtstag_speichert_null(array $feld): void
    {
        $this->actingAs($this->admin())
            ->post('/users/store', $this->formularDaten($feld))
            ->assertRedirect(route('user'));

        $this->assertDatabaseHas('users', ['email' => 'neu@example.test']);
        $this->assertNull(DB::table('users')->where('email', 'neu@example.test')->value('birthday'));
    }

    // AK2
    #[DataProvider('leereWerteFuerGeburtstag')]
    public function test_ak2_update_ohne_geburtstag_setzt_null(array $feld): void
    {
        $ziel = $this->bestehenderNutzer();
        $this->assertNotNull(DB::table('users')->where('id', $ziel->id)->value('birthday'), 'Vorbedingung: Geburtstag ist gesetzt');

        $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->formularDaten(['email' => 'alt@example.test', 'password' => '', 'password2' => ''] + $feld))
            ->assertRedirect();

        $this->assertNull(DB::table('users')->where('id', $ziel->id)->value('birthday'));
    }

    // AK2 (Gegenprobe: ein angegebener Geburtstag bleibt erhalten)
    public function test_ak2_angegebener_geburtstag_wird_gespeichert(): void
    {
        $this->actingAs($this->admin())
            ->post('/users/store', $this->formularDaten(['birthday' => '1985-03-09']))
            ->assertRedirect(route('user'));

        $this->assertStringStartsWith('1985-03-09', (string) DB::table('users')->where('email', 'neu@example.test')->value('birthday'));
    }

    // ---------------------------------------------------------------- Randfall 3

    // Randfall 3
    public function test_randfall3_store_speichert_stundensatz_mit_wert_unveraendert(): void
    {
        $this->actingAs($this->admin())
            ->post('/users/store', $this->formularDaten(['hourly_rate' => '12.50']))
            ->assertRedirect(route('user'));

        $this->assertSame(12.5, $this->gespeicherterStundensatz('neu@example.test'));
    }

    // Randfall 3
    public function test_randfall3_update_speichert_stundensatz_mit_wert_unveraendert(): void
    {
        $ziel = $this->bestehenderNutzer();

        $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->formularDaten(['email' => 'alt@example.test', 'password' => '', 'password2' => '', 'hourly_rate' => '12.50']))
            ->assertRedirect();

        $this->assertSame(12.5, $this->gespeicherterStundensatz('alt@example.test'));
    }

    // ---------------------------------------------------------------- Randfall 4

    // Randfall 4
    public function test_randfall4_formular_sendet_bei_ausgeschaltetem_stundensatz_kein_feld(): void
    {
        $this->assertFalse((bool) config('userauth.user.hourly_rate'), 'Vorbedingung: Default ausgeschaltet');

        $this->actingAs($this->admin())->get('/users/create')
            ->assertOk()
            ->assertDontSee('name="hourly_rate"', false);
    }

    // Randfall 4
    public function test_randfall4_store_ohne_feld_bei_ausgeschaltetem_stundensatz_speichert_null_euro(): void
    {
        config()->set('userauth.user.hourly_rate', false);

        $this->actingAs($this->admin())
            ->post('/users/store', $this->formularDaten())
            ->assertRedirect(route('user'));

        $this->assertSame(0.0, $this->gespeicherterStundensatz('neu@example.test'));
    }

    // Randfall 4
    public function test_randfall4_update_ohne_feld_bei_ausgeschaltetem_stundensatz_setzt_null_euro(): void
    {
        config()->set('userauth.user.hourly_rate', false);
        $ziel = $this->bestehenderNutzer();

        $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->formularDaten(['email' => 'alt@example.test', 'password' => '', 'password2' => '']))
            ->assertRedirect();

        $this->assertSame(0.0, $this->gespeicherterStundensatz('alt@example.test'));
    }
}
