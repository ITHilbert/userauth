<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Http\Requests\NeuesPasswortRegeln;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\RegistrierungHilfen;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Häufige Passwörter ablehnen per Config `userauth.passwort.haeufige_ablehnen` (passwort-haeufige, #2342).
 */
final class PasswortHaeufigeTest extends TestCase
{
    use RefreshDatabase;
    use RegistrierungHilfen;

    private const ROLLE_NORMAL = 3;

    private const AKTUELL = 'Aktuelles-Passwort-1!';

    private const HAEUFIG = 'password1234';

    private const SELTEN = 'blauerhafenkran';

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        $this->grundeinstellung();
        $this->rollenAnlegen();

        foreach (['user_create', 'user_edit'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('role_permission')->insert(['role_id' => self::ROLLE_NORMAL, 'permission_id' => $i + 1]);
        }
    }

    private function nutzer(string $email = 'alt@example.test'): User
    {
        return User::factory()->create([
            'role_id' => self::ROLLE_NORMAL,
            'email' => $email,
            'password' => Hash::make(self::AKTUELL),
        ]);
    }

    private function hash(User $nutzer): string
    {
        return (string) DB::table('users')->where('id', $nutzer->id)->value('password');
    }

    private function aendern(User $nutzer, string $passwort)
    {
        return $this->actingAs($nutzer)->from(route('password.edit'))->post(route('password.update'), [
            'aktuelles_passwort' => self::AKTUELL,
            'password' => $passwort,
            'password_confirmation' => $passwort,
        ]);
    }

    private function regelPasst(string $passwort): bool
    {
        return Validator::make(
            ['password' => $passwort, 'password_confirmation' => $passwort],
            NeuesPasswortRegeln::regeln(),
        )->passes();
    }

    private function fehlerText(string $passwort): string
    {
        $validator = Validator::make(
            ['password' => $passwort, 'password_confirmation' => $passwort],
            NeuesPasswortRegeln::regeln(),
        );

        return (string) ($validator->errors()->first('password') ?? '');
    }

    // AK1
    public function test_ak1_aendern_lehnt_haeufiges_passwort_ab_und_laesst_hash(): void
    {
        config()->set('userauth.passwort.haeufige_ablehnen', true);
        config()->set('userauth.passwort.min_laenge', 12);
        $nutzer = $this->nutzer();
        $vorher = $this->hash($nutzer);

        $this->aendern($nutzer, self::HAEUFIG)->assertSessionHasErrors('password');
        $this->assertSame($vorher, $this->hash($nutzer));

        // Gegenprobe: dieselbe Anfrage mit seltenem Passwort geht durch.
        $this->aendern($nutzer, self::SELTEN)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::SELTEN, $this->hash($nutzer)));
    }

    // AK2
    public function test_ak2_schreibweise_und_randleerzeichen_zaehlen_nicht(): void
    {
        config()->set('userauth.passwort.haeufige_ablehnen', true);
        config()->set('userauth.passwort.min_laenge', 12);

        $this->assertFalse($this->regelPasst('Password1234'));
        $this->assertFalse($this->regelPasst(' password1234 '));

        // Gegenprobe: ohne Schalter werden dieselben Werte angenommen, und seltenes Passwort passt.
        $this->assertTrue($this->regelPasst(self::SELTEN));
        config()->set('userauth.passwort.haeufige_ablehnen', false);
        $this->assertTrue($this->regelPasst('Password1234'));
        $this->assertTrue($this->regelPasst(' password1234 '));
    }

    // AK3
    public function test_ak3_seltenes_passwort_wird_angenommen(): void
    {
        config()->set('userauth.passwort.haeufige_ablehnen', true);
        config()->set('userauth.passwort.min_laenge', 12);

        // Gegenprobe: der Schalter greift (häufiges Passwort abgelehnt), das seltene nicht.
        $this->assertFalse($this->regelPasst(self::HAEUFIG));
        $this->assertTrue($this->regelPasst(self::SELTEN));
    }

    // AK4
    public function test_ak4_standard_false_und_fehlender_schluessel_lassen_haeufiges_durch(): void
    {
        config()->set('userauth.passwort.min_laenge', 12);
        $nutzer = $this->nutzer();

        config()->set('userauth.passwort.haeufige_ablehnen', false);
        $this->aendern($nutzer, self::HAEUFIG)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::HAEUFIG, $this->hash($nutzer)));

        $config = config('userauth');
        unset($config['passwort']['haeufige_ablehnen']);
        config()->set('userauth', $config);
        $this->assertNull(config('userauth.passwort.haeufige_ablehnen'));
        $this->assertTrue($this->regelPasst(self::HAEUFIG));

        // Gegenprobe: mit true wird dasselbe Passwort abgelehnt.
        config()->set('userauth.passwort.haeufige_ablehnen', true);
        $this->assertFalse($this->regelPasst(self::HAEUFIG));
    }

    // AK5
    public function test_ak5_zuruecksetzen_mit_token_lehnt_haeufiges_passwort_ab(): void
    {
        config()->set('userauth.passwort.haeufige_ablehnen', true);
        config()->set('userauth.passwort.min_laenge', 12);
        $nutzer = $this->nutzer();
        $this->post(route('password.sendtocken'), ['email' => $nutzer->email])->assertRedirect();
        $nachrichten = $this->nachrichtenAn($nutzer->email);
        $this->assertNotEmpty($nachrichten, 'keine Reset-Mail');
        $pfad = (string) parse_url((string) $this->linkAusNachricht(end($nachrichten)), PHP_URL_PATH);
        $this->assertSame(1, preg_match('~password/editwithtoken/([^/]+)~', $pfad, $treffer));
        $token = urldecode($treffer[1]);
        $vorher = $this->hash($nutzer);
        $daten = fn (string $pw) => [
            'email' => $nutzer->email, 'pwtoken' => $token, 'password' => $pw, 'password_confirmation' => $pw,
        ];

        $this->from('/x')->post(route('password.updatewithtoken'), $daten(self::HAEUFIG))->assertSessionHasErrors('password');
        $this->assertSame($vorher, $this->hash($nutzer));

        // Gegenprobe: gleicher Token, seltenes Passwort.
        $this->from('/x')->post(route('password.updatewithtoken'), $daten(self::SELTEN))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::SELTEN, $this->hash($nutzer)));
    }

    // AK5
    public function test_ak5_selbstregistrierung_lehnt_haeufiges_passwort_ab(): void
    {
        config()->set('userauth.passwort.haeufige_ablehnen', true);
        config()->set('userauth.passwort.min_laenge', 12);

        $this->registrieren(['password' => self::HAEUFIG, 'password_confirmation' => self::HAEUFIG])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'kunde@example.test']);

        // Gegenprobe: mit seltenem Passwort wird das Konto angelegt.
        $this->registrieren(['password' => self::SELTEN, 'password_confirmation' => self::SELTEN])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'kunde@example.test']);
    }

    // AK5
    public function test_ak5_admin_anlegen_und_aendern_lehnen_haeufiges_passwort_ab(): void
    {
        config()->set('userauth.passwort.haeufige_ablehnen', true);
        config()->set('userauth.passwort.min_laenge', 12);
        $admin = $this->nutzer('admin@example.test');
        $ziel = $this->nutzer('ziel@example.test');
        $vorher = $this->hash($ziel);
        $felder = fn (string $pw, array $extra = []) => array_merge([
            'name' => 'Neuer Nutzer', 'email' => 'neu@example.test',
            'password' => $pw, 'password2' => $pw, 'role_id' => self::ROLLE_NORMAL,
        ], $extra);

        $this->actingAs($admin)->post('/users/store', $felder(self::HAEUFIG))->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'neu@example.test']);

        $this->actingAs($admin)->post('/users/update/'.$ziel->id, $felder(self::HAEUFIG, ['email' => 'ziel2@example.test']))
            ->assertSessionHasErrors('password');
        $this->assertSame($vorher, $this->hash($ziel));

        // Gegenprobe: mit seltenem Passwort wird angelegt.
        $this->actingAs($admin)->post('/users/store', $felder(self::SELTEN))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'neu@example.test']);
    }

    // AK6
    public function test_ak6_meldung_in_de_und_en_ohne_das_passwort(): void
    {
        config()->set('userauth.passwort.haeufige_ablehnen', true);
        config()->set('userauth.passwort.min_laenge', 12);

        app()->setLocale('de');
        $de = $this->fehlerText(self::HAEUFIG);
        $this->assertSame('Dieses Passwort ist zu verbreitet. Bitte wählen Sie ein anderes.', $de);
        $this->assertSame($de, __('userauth::password.haeufig'));

        app()->setLocale('en');
        $en = $this->fehlerText(self::HAEUFIG);
        $this->assertNotSame('userauth::password.haeufig', $en);
        $this->assertSame($en, __('userauth::password.haeufig'));
        $this->assertNotSame($de, $en);

        $this->assertStringNotContainsString(self::HAEUFIG, $de);
        $this->assertStringNotContainsString(self::HAEUFIG, $en);

        // Gegenprobe: bei seltenem Passwort keine Meldung.
        $this->assertSame('', $this->fehlerText(self::SELTEN));
    }

    // AK7
    public function test_ak7_keine_netzabfrage_und_tausend_pruefungen_unter_einer_sekunde(): void
    {
        Http::fake();
        config()->set('userauth.passwort.haeufige_ablehnen', true);
        config()->set('userauth.passwort.min_laenge', 12);

        // Erste Prüfung lädt die Liste (je Prozess einmal); Gegenprobe: Regel greift.
        $this->assertFalse($this->regelPasst(self::HAEUFIG));

        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $this->regelPasst($i % 2 === 0 ? self::HAEUFIG : self::SELTEN.$i);
        }
        $dauer = microtime(true) - $start;

        Http::assertNothingSent();
        $this->assertLessThan(1.0, $dauer, '1000 Prüfungen dauerten '.round($dauer, 2).' s');
    }

    // AK8
    public function test_ak8_config_datei_enthaelt_schluessel_mit_standard_false_und_env(): void
    {
        $config = require __DIR__.'/../../src/Config/config.php';

        $this->assertArrayHasKey('haeufige_ablehnen', $config['passwort'] ?? []);
        $this->assertFalse((bool) $config['passwort']['haeufige_ablehnen']);

        putenv('USERAUTH_PASSWORT_HAEUFIGE_ABLEHNEN=true');
        try {
            $mitEnv = require __DIR__.'/../../src/Config/config.php';
        } finally {
            putenv('USERAUTH_PASSWORT_HAEUFIGE_ABLEHNEN');
        }
        $this->assertContains($mitEnv['passwort']['haeufige_ablehnen'] ?? null, [true, 'true', '1', 1]);
    }

    // AK8
    public function test_ak8_readme_nennt_einstellung_quelle_lizenz_und_changelog_hat_4_5_0(): void
    {
        $readme = (string) file_get_contents(__DIR__.'/../../README.md');
        foreach (['haeufige_ablehnen', 'SecLists', 'MIT'] as $begriff) {
            $this->assertStringContainsString($begriff, $readme);
        }
        $this->assertStringContainsString('4.5.0', (string) file_get_contents(__DIR__.'/../../CHANGELOG.md'));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function configWerte(): array
    {
        return [
            'abc' => ['abc', false], 'leer' => ['', false],
            'true-Text' => ['true', true], '1-Text' => ['1', true], 'on' => ['on', true],
        ];
    }

    // Randfall 1 (ungültiger Wert wirkt wie false; „true“, „1“, „on“ wie true)
    #[DataProvider('configWerte')]
    public function test_randfall_string_werte_der_config(mixed $wert, bool $wirktAlsTrue): void
    {
        config()->set('userauth.passwort.min_laenge', 12);

        // Gegenprobe: mit echtem true wird abgelehnt, mit echtem false nicht.
        config()->set('userauth.passwort.haeufige_ablehnen', true);
        $this->assertFalse($this->regelPasst(self::HAEUFIG));
        config()->set('userauth.passwort.haeufige_ablehnen', false);
        $this->assertTrue($this->regelPasst(self::HAEUFIG));

        config()->set('userauth.passwort.haeufige_ablehnen', $wert);
        $this->assertSame(! $wirktAlsTrue, $this->regelPasst(self::HAEUFIG));
    }
}
