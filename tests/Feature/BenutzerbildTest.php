<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Benutzerbild über die Disk `public`, eine Stelle für alle Wege (userauth-benutzerbild).
 * Abgedeckt: AK1–AK5 und die Randfälle.
 *
 * Die Disk `local` wurzelt wie im Laravel-Standard in `storage/app/private` (hier ein eigener Unterordner),
 * die Disk `public` ist gefälscht.
 */
final class BenutzerbildTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    private string $localRoot = '';

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        config()->set('userauth.password_policy.enabled', false);

        $this->localRoot = storage_path('app/private/test-benutzerbild-'.uniqid());
        File::ensureDirectoryExists($this->localRoot);
        config()->set('filesystems.disks.local.root', $this->localRoot);
        Storage::fake('public');

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

    protected function tearDown(): void
    {
        User::flushEventListeners();
        if ($this->localRoot !== '') {
            File::deleteDirectory($this->localRoot);
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------- Hilfen

    private function admin(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'admin@example.test']);
    }

    private function zielNutzer(?string $image): User
    {
        $nutzer = User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'ziel@example.test']);
        DB::table('users')->where('id', $nutzer->id)->update(['image' => $image]);

        return $nutzer->fresh();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formular(array $extra = []): array
    {
        return array_merge([
            'name' => 'Neuer Nutzer',
            'email' => 'neu@example.test',
            'password' => 'Neues-Passwort-2026!',
            'password2' => 'Neues-Passwort-2026!',
            'role_id' => self::ROLLE_NORMAL,
        ], $extra);
    }

    private function bild(): UploadedFile
    {
        return UploadedFile::fake()->image('neu.jpg');
    }

    private function bildSpalte(User $nutzer): ?string
    {
        $wert = DB::table('users')->where('id', $nutzer->id)->value('image');

        return $wert === null ? null : (string) $wert;
    }

    /**
     * @return list<string>
     */
    private function dateienUnterLocalPublic(): array
    {
        $dateien = [];
        if (is_dir($this->localRoot)) {
            foreach (File::allFiles($this->localRoot) as $datei) {
                $dateien[] = $datei->getPathname();
            }
        }

        return $dateien;
    }

    /**
     * Gegenprobe der Ausgangslage: die Disk `local` wurzelt im privaten Ordner, `public` ist leer.
     */
    private function assertAusgangslage(): void
    {
        $this->assertSame($this->localRoot, config('filesystems.disks.local.root'));
        $this->assertSame([], Storage::disk('public')->allFiles('users'), 'Ausgangslage: Disk public leer');
        $this->assertSame([], $this->dateienUnterLocalPublic(), 'Ausgangslage: unter local nichts');
    }

    /**
     * Prüft die Ablage eines neuen Bildes: Disk public unter users/, Spalte storage/users/<datei>, nichts unter local.
     */
    private function assertBildAufPublic(User $nutzer): void
    {
        $spalte = $this->bildSpalte($nutzer);
        $this->assertNotNull($spalte, 'image gesetzt');
        $this->assertMatchesRegularExpression('#^storage/users/[^/]+$#', $spalte);
        $this->assertCount(1, Storage::disk('public')->allFiles('users'), 'genau eine Datei auf Disk public unter users/');
        $this->assertTrue(Storage::disk('public')->exists('users/'.basename($spalte)), 'Spalte verweist auf die Datei auf der Disk public');
        $this->assertSame([], $this->dateienUnterLocalPublic(), 'nichts unter der Wurzel der Disk local');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function wege(): array
    {
        return ['user.update' => ['update'], 'usericon.update' => ['icon']];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function hochladen(string $weg, User $ziel, array $extra = []): TestResponse
    {
        if ($weg === 'update') {
            return $this->actingAs($this->admin())->post(route('user.update', $ziel->id), $this->formular(['email' => 'neu-ziel@example.test', 'image' => $this->bild()] + $extra));
        }

        return $this->actingAs($ziel)->post(route('usericon.update'), ['image' => $this->bild()] + $extra);
    }

    // ---------------------------------------------------------------- AK1

    // AK1
    public function test_ak1_store_legt_bild_auf_disk_public_ab_unabhaengig_von_der_local_wurzel(): void
    {
        $this->assertAusgangslage();

        $this->actingAs($this->admin())
            ->post(route('user.store'), $this->formular(['image' => $this->bild()]))
            ->assertSessionHasNoErrors();

        $neu = User::query()->where('email', 'neu@example.test')->firstOrFail();
        $this->assertBildAufPublic($neu);
    }

    // AK1
    #[DataProvider('wege')]
    public function test_ak1_update_und_icon_legen_bild_auf_disk_public_ab_unabhaengig_von_der_local_wurzel(string $weg): void
    {
        $ziel = $this->zielNutzer(null);
        $this->assertAusgangslage();

        $antwort = $this->hochladen($weg, $ziel);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $this->assertBildAufPublic($ziel);
    }

    // AK1
    public function test_ak1_spalte_image_enthaelt_wie_bisher_storage_users_datei(): void
    {
        $this->actingAs($this->admin())
            ->post(route('user.store'), $this->formular(['image' => $this->bild()]))
            ->assertSessionHasNoErrors();

        $neu = User::query()->where('email', 'neu@example.test')->firstOrFail();
        $spalte = (string) $this->bildSpalte($neu);
        $this->assertStringStartsWith('storage/users/', $spalte);
        $this->assertTrue(Storage::disk('public')->exists(substr($spalte, strlen('storage/'))));
    }

    // ---------------------------------------------------------------- AK2

    // AK2
    #[DataProvider('wege')]
    public function test_ak2_altes_bild_wird_von_der_disk_public_geloescht_auch_bei_private_local_wurzel(string $weg): void
    {
        Storage::disk('public')->put('users/alt.jpg', 'altes-bild');
        $ziel = $this->zielNutzer('storage/users/alt.jpg');
        $this->assertTrue(Storage::disk('public')->exists('users/alt.jpg'), 'Ausgangslage: altes Bild liegt auf Disk public');
        $this->assertSame($this->localRoot, config('filesystems.disks.local.root'));

        $antwort = $this->hochladen($weg, $ziel);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $this->assertFalse(Storage::disk('public')->exists('users/alt.jpg'), 'altes Bild gelöscht');
        $this->assertBildAufPublic($ziel);
        $this->assertNotSame('storage/users/alt.jpg', $this->bildSpalte($ziel));
    }

    // ---------------------------------------------------------------- AK3

    // AK3
    public function test_ak3_store_raeumt_bei_exception_beim_anlegen_datei_und_benutzer_auf(): void
    {
        $admin = $this->admin();
        // Gegenprobe: ohne Fehler wird Datei und Benutzer angelegt.
        $this->actingAs($admin)->post(route('user.store'), $this->formular(['email' => 'ok@example.test', 'image' => $this->bild()]))->assertSessionHasNoErrors();
        $this->assertCount(1, Storage::disk('public')->allFiles('users'), 'Gegenprobe: Datei liegt');
        $this->assertDatabaseHas('users', ['email' => 'ok@example.test']);
        $benutzerVorher = DB::table('users')->count();

        User::creating(function (): void {
            throw new RuntimeException('Speichern scheitert');
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('user.store'), $this->formular(['email' => 'fehler@example.test', 'image' => $this->bild()]));
            $this->fail('Exception erwartet');
        } catch (RuntimeException $e) {
            $this->assertSame('Speichern scheitert', $e->getMessage());
        }

        $this->assertCount(1, Storage::disk('public')->allFiles('users'), 'keine neue Datei auf Disk public');
        $this->assertSame([], $this->dateienUnterLocalPublic());
        $this->assertSame($benutzerVorher, DB::table('users')->count());
        $this->assertDatabaseMissing('users', ['email' => 'fehler@example.test']);
    }

    // AK3
    public function test_ak3_store_raeumt_bei_exception_nach_dem_insert_in_der_transaktion_datei_und_benutzer_auf(): void
    {
        $admin = $this->admin();
        $benutzerVorher = DB::table('users')->count();
        $this->assertAusgangslage();

        User::created(function (): void {
            throw new RuntimeException('Fehler nach dem Insert');
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('user.store'), $this->formular(['email' => 'fehler@example.test', 'image' => $this->bild()]));
            $this->fail('Exception erwartet');
        } catch (RuntimeException $e) {
            $this->assertSame('Fehler nach dem Insert', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('public')->allFiles('users'), 'keine Datei auf Disk public');
        $this->assertSame([], $this->dateienUnterLocalPublic());
        $this->assertSame($benutzerVorher, DB::table('users')->count(), 'kein neuer Benutzer');
        $this->assertDatabaseMissing('users', ['email' => 'fehler@example.test']);
    }

    // ---------------------------------------------------------------- AK4

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function wegeOhneBild(): array
    {
        return [
            'update, image null' => ['update', null],
            'update, image leer' => ['update', ''],
            'icon, image null' => ['icon', null],
            'icon, image leer' => ['icon', ''],
        ];
    }

    // AK4
    #[DataProvider('wegeOhneBild')]
    public function test_ak4_upload_bei_benutzer_ohne_bild_antwortet_ohne_5xx_und_setzt_image(string $weg, ?string $image): void
    {
        $ziel = $this->zielNutzer($image);
        $this->assertContains($this->bildSpalte($ziel), [null, ''], 'Ausgangslage: kein Bild');

        $antwort = $this->hochladen($weg, $ziel);

        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein 5xx');
        $this->assertBildAufPublic($ziel);
    }

    // ---------------------------------------------------------------- AK5

    private function controllerQuelltext(): string
    {
        return (string) file_get_contents(__DIR__.'/../../src/Http/Controllers/UserController.php');
    }

    // AK5
    public function test_ak5_controller_hat_kein_storage_path_app_public_und_kein_unlink(): void
    {
        $quelle = $this->controllerQuelltext();
        $this->assertStringContainsString('function bildAblegen', $quelle, 'Gegenprobe: Quelltext geladen');

        $this->assertStringNotContainsString("storage_path('app/public", $quelle);
        $this->assertStringNotContainsString('storage_path("app/public', $quelle);
        $this->assertDoesNotMatchRegularExpression('/\bunlink\s*\(/', $quelle);
    }

    // AK5
    public function test_ak5_store_update_und_icon_nutzen_die_helfer_und_legen_nicht_selbst_ab(): void
    {
        $quelle = $this->controllerQuelltext();
        foreach (['store', 'update', 'usericon_update'] as $methode) {
            $this->assertSame(1, preg_match('/function '.$methode.'\(.*?(?=\n    (?:public|private|protected) function )/s', $quelle, $treffer), $methode.' gefunden');
            $this->assertStringContainsString('bildAblegen(', $treffer[0], $methode.' nutzt bildAblegen()');
            $this->assertStringNotContainsString('->store(', $treffer[0], $methode.' legt nicht selbst ab');
            $this->assertStringNotContainsString('Storage::', $treffer[0], $methode.' löscht nicht selbst');
        }
    }

    // AK5
    public function test_ak5_ablegen_und_loeschen_stehen_nur_in_den_helfern(): void
    {
        $quelle = $this->controllerQuelltext();
        $ohneHelfer = (string) preg_replace('/(?:\n    \/\*\*(?:(?!\*\/).)*\*\/)?\n    private function (?:bildAblegen|bildAbschliessen)\(.*?\n    }\n/s', '', $quelle);

        $this->assertNotSame($quelle, $ohneHelfer, 'Gegenprobe: Helfer wurden entfernt');
        $this->assertStringNotContainsString('->store(', $ohneHelfer);
        $this->assertStringNotContainsString('Storage::', $ohneHelfer);
        // einzelwort: Quelltext des Controllers, `unlink` kommt nur beim Löschen eines Bildes vor
        $this->assertStringNotContainsString('unlink', $ohneHelfer);
    }

    // ---------------------------------------------------------------- Randfälle

    // Randfall: Altes Bild existiert nicht mehr auf der Disk
    #[DataProvider('wege')]
    public function test_randfall_altes_bild_fehlt_auf_der_disk_ohne_fehler_neues_bild_gespeichert(string $weg): void
    {
        $ziel = $this->zielNutzer('storage/users/verschwunden.jpg');
        $this->assertFalse(Storage::disk('public')->exists('users/verschwunden.jpg'), 'Ausgangslage: Datei fehlt');

        $antwort = $this->hochladen($weg, $ziel);

        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein Fehler');
        $this->assertBildAufPublic($ziel);
    }

    // Randfall: Alter Wert ohne Präfix storage/ (Altdaten) wird relativ zur Disk gedeutet
    #[DataProvider('wege')]
    public function test_randfall_altdaten_ohne_praefix_werden_relativ_zur_disk_geloescht(string $weg): void
    {
        Storage::disk('public')->put('users/altdaten.jpg', 'altes-bild');
        $ziel = $this->zielNutzer('users/altdaten.jpg');
        $this->assertTrue(Storage::disk('public')->exists('users/altdaten.jpg'), 'Ausgangslage: Datei liegt');

        $antwort = $this->hochladen($weg, $ziel);

        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein Fehler');
        $this->assertFalse(Storage::disk('public')->exists('users/altdaten.jpg'), 'Altdaten-Bild gelöscht');
        $this->assertBildAufPublic($ziel);
    }

    // Randfall: Altdaten ohne Präfix, liegt dort nichts, wird nichts gelöscht
    #[DataProvider('wege')]
    public function test_randfall_altdaten_ohne_praefix_ohne_datei_loeschen_nichts(string $weg): void
    {
        Storage::disk('public')->put('users/fremd.jpg', 'unbeteiligt');
        $ziel = $this->zielNutzer('nirgendwo.jpg');
        $this->assertTrue(Storage::disk('public')->exists('users/fremd.jpg'), 'Ausgangslage: andere Datei liegt');

        $antwort = $this->hochladen($weg, $ziel);

        $this->assertLessThan(500, $antwort->getStatusCode(), 'kein Fehler');
        $this->assertTrue(Storage::disk('public')->exists('users/fremd.jpg'), 'andere Datei bleibt');
        $this->assertCount(2, Storage::disk('public')->allFiles('users'), 'nur das neue Bild kam dazu');
        $this->assertNotSame('nirgendwo.jpg', $this->bildSpalte($ziel));
    }
}
