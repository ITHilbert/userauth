<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Admin legt Benutzer an bzw. bearbeitet sie: Passwort nach denselben Regeln wie beim eigenen Ändern.
 * Abgedeckt: AK1–AK5 und beide Randfälle (userauth-admin-passwort).
 *
 * Formularfelder des Admins: password, password2 (nicht password_confirmation).
 */
final class AdminPasswortTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    private const AKTUELLES_PASSWORT = 'Aktuelles-Passwort-1!';

    private const FRUEHERES_PASSWORT = 'Fruehere-Passwort-2!';

    private const NEUES_PASSWORT = 'Neues-Passwort-2026!';

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        config()->set('userauth.password_policy.enabled', false);

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
     * @return array<string, array{0: bool}>
     */
    public static function policyZustaende(): array
    {
        return ['ohne password_policy' => [false], 'mit password_policy' => [true]];
    }

    /**
     * Passwörter, die NeuesPasswortRegeln verletzen (zu kurz, kein String).
     *
     * @return array<string, array{0: bool, 1: mixed}>
     */
    public static function schwachePasswoerterMitPolicy(): array
    {
        $faelle = [];
        foreach (self::policyZustaende() as $policyName => [$policy]) {
            foreach (['sieben Zeichen' => 'Abc1234', 'ein Zeichen' => 'a', 'Array statt Text' => ['Gueltiges-Passwort-1!']] as $name => $wert) {
                $faelle[$policyName.', '.$name] = [$policy, $wert];
            }
        }

        return $faelle;
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'admin@example.test']);
    }

    private function bestehenderNutzer(): User
    {
        return User::factory()->create([
            'role_id' => self::ROLLE_NORMAL,
            'email' => 'alt@example.test',
            'name' => 'Alter Name',
            'password' => Hash::make(self::AKTUELLES_PASSWORT),
        ]);
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
            'password' => self::NEUES_PASSWORT,
            'password2' => self::NEUES_PASSWORT,
            'role_id' => self::ROLLE_NORMAL,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function updateDaten(array $extra = []): array
    {
        return $this->formularDaten(['name' => 'Neuer Name', 'email' => 'neu-alt@example.test'] + $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function zeile(User $nutzer): array
    {
        return (array) DB::table('users')->where('id', $nutzer->id)->first();
    }

    private function historienAnzahl(User $nutzer): int
    {
        return DB::table('user_password_histories')->where('user_id', $nutzer->id)->count();
    }

    private function policyAn(): void
    {
        config()->set('userauth.password_policy.enabled', true);
        config()->set('userauth.password_policy.prevent_reuse_last_passwords', 3);
    }

    /**
     * Gegenprobe: Der Nutzer selbst bekommt dasselbe Passwort über „Passwort ändern“ abgelehnt.
     */
    private function assertEigenesAendernLehntAb(User $nutzer, mixed $passwort): void
    {
        $this->actingAs($nutzer)
            ->from(route('password.edit'))
            ->post(route('password.update'), ['password' => $passwort, 'password_confirmation' => $passwort])
            ->assertSessionHasErrors('password');
        $this->assertSame($nutzer->password, DB::table('users')->where('id', $nutzer->id)->value('password'), 'Gegenprobe: eigenes Ändern darf nichts speichern.');
    }

    /**
     * Wiederverwendbare Ausgangslage: aktuelles Passwort + eine ältere Historienzeile, Policy aktiv.
     */
    private function nutzerMitHistorie(): User
    {
        $this->policyAn();
        $nutzer = $this->bestehenderNutzer();
        DB::table('users')->where('id', $nutzer->id)->update(['password_changed_at' => now()->subDays(10)]);
        DB::table('user_password_histories')->insert([
            'user_id' => $nutzer->id, 'password' => Hash::make(self::FRUEHERES_PASSWORT),
            'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20),
        ]);

        return $nutzer->fresh();
    }

    // ---------------------------------------------------------------- AK1

    // AK1
    #[DataProvider('schwachePasswoerterMitPolicy')]
    public function test_ak1_store_lehnt_regelverletzendes_passwort_am_feld_password_ab_und_legt_nichts_an(bool $policy, mixed $passwort): void
    {
        config()->set('userauth.password_policy.enabled', $policy);
        $admin = $this->admin();
        $vorher = DB::table('users')->count();

        // Gegenprobe: dasselbe Passwort wird beim eigenen Ändern abgelehnt (Regel greift).
        $this->assertEigenesAendernLehntAb($admin, $passwort);

        $antwort = $this->actingAs($admin)->post('/users/store', $this->formularDaten(['password' => $passwort, 'password2' => $passwort]));

        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein Serverfehler');
        $antwort->assertSessionHasErrors('password');
        $this->assertSame($vorher, DB::table('users')->count());
        $this->assertDatabaseMissing('users', ['email' => 'neu@example.test']);
    }

    // AK1
    #[DataProvider('schwachePasswoerterMitPolicy')]
    public function test_ak1_update_lehnt_regelverletzendes_passwort_am_feld_password_ab_und_laesst_nutzer_unveraendert(bool $policy, mixed $passwort): void
    {
        config()->set('userauth.password_policy.enabled', $policy);
        $ziel = $this->bestehenderNutzer();
        $vorher = $this->zeile($ziel);

        // Gegenprobe: dasselbe Passwort wird beim eigenen Ändern abgelehnt (Regel greift).
        $this->assertEigenesAendernLehntAb($ziel, $passwort);

        $antwort = $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->updateDaten(['password' => $passwort, 'password2' => $passwort]));

        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein Serverfehler');
        $antwort->assertSessionHasErrors('password');
        $this->assertSame($vorher, $this->zeile($ziel), 'Name, E-Mail und Passwort müssen unverändert bleiben.');
    }

    // AK1
    #[DataProvider('policyZustaende')]
    public function test_ak1_store_ohne_password_und_mit_leerem_password_wird_abgelehnt(bool $policy): void
    {
        config()->set('userauth.password_policy.enabled', $policy);
        $admin = $this->admin();
        $vorher = DB::table('users')->count();

        foreach (['leer' => ['password' => '', 'password2' => ''], 'nicht gesetzt' => []] as $name => $felder) {
            $daten = $this->formularDaten();
            unset($daten['password'], $daten['password2']);
            $antwort = $this->actingAs($admin)->post('/users/store', $daten + $felder);

            $this->assertLessThan(500, $antwort->getStatusCode(), "kein Serverfehler ($name)");
            $antwort->assertSessionHasErrors('password');
            $this->assertSame($vorher, DB::table('users')->count(), "nichts angelegt ($name)");
        }
    }

    // ---------------------------------------------------------------- AK2

    // AK2
    public function test_ak2_update_speichert_neues_passwort_und_setzt_password_changed_at(): void
    {
        $this->travelTo(now()->startOfSecond());
        $ziel = $this->bestehenderNutzer();
        $this->assertNull(DB::table('users')->where('id', $ziel->id)->value('password_changed_at'), 'Ausgangslage: noch nie geändert');

        $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->updateDaten())
            ->assertSessionHasNoErrors();

        $neu = $this->zeile($ziel);
        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $neu['password']), 'neues Passwort gespeichert');
        $this->assertFalse(Hash::check(self::AKTUELLES_PASSWORT, $neu['password']), 'altes Passwort gilt nicht mehr');
        $this->assertNotNull($neu['password_changed_at'], 'password_changed_at gesetzt');
        $this->assertEqualsWithDelta(now()->getTimestamp(), strtotime((string) $neu['password_changed_at']), 5);
    }

    // AK2
    public function test_ak2_update_schreibt_bei_aktiver_policy_den_alten_hash_in_die_historie(): void
    {
        $ziel = $this->nutzerMitHistorie();
        $alterHash = $ziel->password;
        $vorher = $this->historienAnzahl($ziel);

        $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->updateDaten())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('user_password_histories', ['user_id' => $ziel->id, 'password' => $alterHash]);
        $this->assertSame($vorher + 1, $this->historienAnzahl($ziel));
        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $this->zeile($ziel)['password']), 'Gegenprobe: neues Passwort gespeichert');
    }

    // AK2
    public function test_ak2_update_schreibt_ohne_policy_nichts_in_die_historie(): void
    {
        $ziel = $this->bestehenderNutzer();

        $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->updateDaten())
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $this->zeile($ziel)['password']), 'Gegenprobe: Passwort wurde gesetzt');
        $this->assertSame(0, $this->historienAnzahl($ziel));
    }

    // ---------------------------------------------------------------- AK3

    /**
     * @return array<string, array{0: string}>
     */
    public static function wiederverwendetePasswoerter(): array
    {
        return [
            'aktuelles Passwort' => [self::AKTUELLES_PASSWORT],
            'Passwort aus der Historie' => [self::FRUEHERES_PASSWORT],
        ];
    }

    // AK3
    #[DataProvider('wiederverwendetePasswoerter')]
    public function test_ak3_wiederverwendetes_passwort_wird_am_feld_password_abgelehnt_und_nichts_gespeichert(string $passwort): void
    {
        $ziel = $this->nutzerMitHistorie();
        $vorher = $this->zeile($ziel);
        $historieVorher = $this->historienAnzahl($ziel);

        // Gegenprobe: Aufbau greift — Historie enthält das frühere Passwort, eigenes Ändern lehnt es ab.
        $this->assertSame(1, $historieVorher);
        $this->assertTrue(Hash::check(self::FRUEHERES_PASSWORT, (string) DB::table('user_password_histories')->where('user_id', $ziel->id)->value('password')));
        $this->assertEigenesAendernLehntAb($ziel, $passwort);

        $antwort = $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->updateDaten(['password' => $passwort, 'password2' => $passwort]));

        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein Serverfehler');
        $antwort->assertSessionHasErrors(['password' => trans('userauth::password.bereits_verwendet')]);
        $this->assertSame($vorher, $this->zeile($ziel), 'Weder Name noch E-Mail noch Passwort noch password_changed_at dürfen sich ändern.');
        $this->assertSame($historieVorher, $this->historienAnzahl($ziel), 'Historie unverändert');
    }

    // AK3
    public function test_ak3_hinweis_erscheint_in_der_gerenderten_seite_am_formular(): void
    {
        $ziel = $this->nutzerMitHistorie();
        $admin = $this->admin();
        $meldung = e(trans('userauth::password.bereits_verwendet'));
        $formular = route('user.edit', $ziel->id);

        // Gegenprobe: Formular ist erreichbar und zeigt die Meldung vorher nicht.
        $this->actingAs($admin)->get($formular)->assertOk()->assertDontSee($meldung, false);

        $this->actingAs($admin)->from($formular)
            ->post('/users/update/'.$ziel->id, $this->updateDaten(['password' => self::AKTUELLES_PASSWORT, 'password2' => self::AKTUELLES_PASSWORT]))
            ->assertSessionHasErrors(['password' => trans('userauth::password.bereits_verwendet')])
            ->assertRedirect($formular);

        $this->actingAs($admin)->get($formular)->assertOk()->assertSee($meldung, false);
    }

    // ---------------------------------------------------------------- AK3 mit Bild-Upload

    private const ALTES_BILD = 'users/test-admin-passwort-alt.jpg';

    /**
     * Nutzer mit vorhandenem Bild (Datei auf der Disk `public`, wie update() sie sucht).
     */
    private function nutzerMitBild(): User
    {
        Storage::disk('public')->put(self::ALTES_BILD, 'altes-bild');

        $nutzer = $this->bestehenderNutzer();
        DB::table('users')->where('id', $nutzer->id)->update(['image' => 'storage/'.self::ALTES_BILD]);

        return $nutzer->fresh();
    }

    // AK3
    public function test_ak3_abgelehntes_passwort_mit_bild_laesst_bild_in_db_und_auf_disk_unveraendert_und_speichert_kein_neues(): void
    {
        Storage::fake('public');
        $this->policyAn();
        $ziel = $this->nutzerMitBild();
        $bildVorher = $ziel->image;
        $this->assertTrue(Storage::disk('public')->exists(self::ALTES_BILD), 'Ausgangslage: altes Bild liegt auf der Disk');
        $this->assertSame([self::ALTES_BILD], Storage::disk('public')->allFiles('users'), 'Ausgangslage: keine neue Datei');

        $antwort = $this->actingAs($this->admin())->post('/users/update/'.$ziel->id, $this->updateDaten([
            'password' => self::AKTUELLES_PASSWORT,
            'password2' => self::AKTUELLES_PASSWORT,
            'image' => UploadedFile::fake()->image('neu.jpg'),
        ]));

        // Gegenprobe: Die Ablehnung kam wirklich.
        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein Serverfehler');
        $antwort->assertSessionHasErrors(['password' => trans('userauth::password.bereits_verwendet')]);

        $this->assertSame($bildVorher, DB::table('users')->where('id', $ziel->id)->value('image'), 'users.image unverändert');
        $this->assertTrue(Storage::disk('public')->exists(self::ALTES_BILD), 'altes Bild darf nicht gelöscht sein');
        $this->assertSame([self::ALTES_BILD], Storage::disk('public')->allFiles('users'), 'neues Bild darf nicht verwaist auf der Disk liegen');
    }

    // AK3
    public function test_ak3_gueltiges_passwort_mit_bild_speichert_neues_bild_und_entfernt_das_alte(): void
    {
        Storage::fake('public');
        $ziel = $this->nutzerMitBild();
        $bildVorher = $ziel->image;

        $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $this->updateDaten(['image' => UploadedFile::fake()->image('neu.jpg')]))
            ->assertSessionHasNoErrors();

        $bildNachher = (string) DB::table('users')->where('id', $ziel->id)->value('image');
        $this->assertNotSame($bildVorher, $bildNachher, 'users.image zeigt auf das neue Bild');
        $this->assertCount(1, Storage::disk('public')->allFiles('users'), 'neues Bild liegt auf der Disk');
        $this->assertTrue(Storage::disk('public')->exists(substr($bildNachher, strlen('storage/'))), 'users.image verweist auf die gespeicherte Datei');
        $this->assertFalse(Storage::disk('public')->exists(self::ALTES_BILD), 'altes Bild entfernt');
        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $this->zeile($ziel)['password']), 'Passwort gesetzt');
    }

    // ---------------------------------------------------------------- AK4

    // AK4
    #[DataProvider('policyZustaende')]
    public function test_ak4_store_speichert_passwort_gehasht_und_setzt_password_changed_at(bool $policy): void
    {
        config()->set('userauth.password_policy.enabled', $policy);
        $this->travelTo(now()->startOfSecond());

        $this->actingAs($this->admin())
            ->post('/users/store', $this->formularDaten())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user'));

        $zeile = (array) DB::table('users')->where('email', 'neu@example.test')->first();
        $this->assertNotEmpty($zeile, 'Benutzer angelegt');
        $this->assertNotSame(self::NEUES_PASSWORT, $zeile['password'], 'nicht im Klartext');
        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $zeile['password']), 'gehasht gespeichert');
        $this->assertNotNull($zeile['password_changed_at'], 'password_changed_at gesetzt');
        $this->assertEqualsWithDelta(now()->getTimestamp(), strtotime((string) $zeile['password_changed_at']), 5);
    }

    // ---------------------------------------------------------------- AK5

    /**
     * @return array<string, array{0: bool}>
     */
    public static function ohnePasswortVarianten(): array
    {
        return ['Felder leer' => [true], 'Felder nicht gesetzt' => [false]];
    }

    // AK5
    #[DataProvider('ohnePasswortVarianten')]
    public function test_ak5_update_ohne_passwort_laesst_hash_changed_at_und_historie_unveraendert_und_speichert_uebrige_felder(bool $leer): void
    {
        $ziel = $this->nutzerMitHistorie();
        $vorher = $this->zeile($ziel);
        $historieVorher = DB::table('user_password_histories')->where('user_id', $ziel->id)->get()->toArray();

        $daten = $this->updateDaten();
        if ($leer) {
            $daten['password'] = '';
            $daten['password2'] = '';
        } else {
            unset($daten['password'], $daten['password2']);
        }

        $this->actingAs($this->admin())
            ->post('/users/update/'.$ziel->id, $daten)
            ->assertSessionHasNoErrors();

        $nachher = $this->zeile($ziel);
        // Gegenprobe: Der Request wurde verarbeitet, die übrigen Felder sind gespeichert.
        $this->assertSame('Neuer Name', $nachher['name']);
        $this->assertSame('neu-alt@example.test', $nachher['email']);

        $this->assertSame($vorher['password'], $nachher['password'], 'Passwort-Hash unverändert');
        $this->assertSame($vorher['password_changed_at'], $nachher['password_changed_at'], 'password_changed_at unverändert');
        $this->assertEquals($historieVorher, DB::table('user_password_histories')->where('user_id', $ziel->id)->get()->toArray(), 'Historie unverändert');
    }

    // ---------------------------------------------------------------- Randfälle

    // Randfall 1
    public function test_randfall1_user_controller_enthaelt_keine_eigene_hash_logik(): void
    {
        $code = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/UserController.php');

        $this->assertFalse(str_contains($code, 'Hash::make'), 'UserController enthält noch Hash::make');
        $this->assertFalse(str_contains($code, 'Facades\\Hash;'), 'UserController importiert noch die Hash-Facade');
        $this->assertDoesNotMatchRegularExpression('/\b(bcrypt|password_hash)\s*\(/', $code);
        $this->assertTrue(str_contains($code, 'PasswortSetzen'), 'Setzen läuft nicht über PasswortSetzen');
    }

    // Randfall 2
    public function test_randfall2_store_lehnt_abweichendes_password2_ab_und_legt_nichts_an(): void
    {
        $admin = $this->admin();
        $vorher = DB::table('users')->count();

        foreach (['abweichend' => ['password2' => 'Anderes-Passwort-7!'], 'leer' => ['password2' => '']] as $name => $felder) {
            $this->actingAs($admin)->post('/users/store', $this->formularDaten($felder))->assertSessionHasErrors('password2');
            $this->assertSame($vorher, DB::table('users')->count(), "nichts angelegt ($name)");
        }

        $daten = $this->formularDaten();
        unset($daten['password2']);
        $this->actingAs($admin)->post('/users/store', $daten)->assertSessionHasErrors('password2');
        $this->assertSame($vorher, DB::table('users')->count(), 'nichts angelegt (nicht gesetzt)');

        // Gegenprobe: mit übereinstimmendem password2 wird angelegt.
        $this->actingAs($admin)->post('/users/store', $this->formularDaten())->assertSessionHasNoErrors();
        $this->assertSame($vorher + 1, DB::table('users')->count());
    }

    // Randfall 2
    public function test_randfall2_update_lehnt_abweichendes_password2_ab_und_laesst_nutzer_unveraendert(): void
    {
        $ziel = $this->bestehenderNutzer();
        $vorher = $this->zeile($ziel);
        $admin = $this->admin();

        foreach (['abweichend' => ['password2' => 'Anderes-Passwort-7!'], 'leer' => ['password2' => '']] as $name => $felder) {
            $this->actingAs($admin)->post('/users/update/'.$ziel->id, $this->updateDaten($felder))->assertSessionHasErrors('password2');
            $this->assertSame($vorher, $this->zeile($ziel), "unverändert ($name)");
        }

        // Gegenprobe: mit übereinstimmendem password2 wird gespeichert.
        $this->actingAs($admin)->post('/users/update/'.$ziel->id, $this->updateDaten())->assertSessionHasNoErrors();
        $this->assertSame('Neuer Name', $this->zeile($ziel)['name']);
    }
}
