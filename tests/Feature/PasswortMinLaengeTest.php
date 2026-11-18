<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\RegistrierungHilfen;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Mindestlänge neuer Passwörter per Config `userauth.passwort.min_laenge` (passwort-min-laenge, #1593).
 */
final class PasswortMinLaengeTest extends TestCase
{
    use RefreshDatabase;
    use RegistrierungHilfen;

    private const ROLLE_NORMAL = 3;

    private const AKTUELL = 'Aktuelles-Passwort-1!';

    private const ELF = 'Abcdefgh1!x';

    private const ZWOELF = 'Abcdefgh1!xy';

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

    private function schluesselEntfernen(): void
    {
        $config = config('userauth');
        unset($config['passwort']['min_laenge']);
        config()->set('userauth', $config);
        $this->assertNull(config('userauth.passwort.min_laenge'));
    }

    private function aendern(User $nutzer, string $passwort)
    {
        return $this->actingAs($nutzer)->from(route('password.edit'))->post(route('password.update'), [
            'aktuelles_passwort' => self::AKTUELL,
            'password' => $passwort,
            'password_confirmation' => $passwort,
        ]);
    }

    /**
     * @return list<string>
     */
    private function tokenUndMail(User $nutzer): array
    {
        $this->post(route('password.sendtocken'), ['email' => $nutzer->email])->assertRedirect();
        $nachrichten = $this->nachrichtenAn($nutzer->email);
        $this->assertNotEmpty($nachrichten, 'keine Reset-Mail');
        $pfad = (string) parse_url((string) $this->linkAusNachricht(end($nachrichten)), PHP_URL_PATH);
        $this->assertSame(1, preg_match('~password/editwithtoken/([^/]+)~', $pfad, $treffer));

        return [urldecode($treffer[1])];
    }

    // AK1
    public function test_ak1_aendern_verlangt_konfigurierte_mindestlaenge_und_laesst_hash_bei_zu_kurz(): void
    {
        config()->set('userauth.passwort.min_laenge', 12);
        $nutzer = $this->nutzer();
        $vorher = $this->hash($nutzer);

        $this->aendern($nutzer, self::ELF)->assertSessionHasErrors('password');
        $this->assertSame($vorher, $this->hash($nutzer));

        // Gegenprobe: mit 12 Zeichen geht dieselbe Anfrage durch.
        $this->aendern($nutzer, self::ZWOELF)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::ZWOELF, $this->hash($nutzer)));
    }

    // AK2
    public function test_ak2_zuruecksetzen_mit_token_verlangt_konfigurierte_mindestlaenge(): void
    {
        config()->set('userauth.passwort.min_laenge', 12);
        $nutzer = $this->nutzer();
        [$token] = $this->tokenUndMail($nutzer);
        $vorher = $this->hash($nutzer);
        $daten = fn (string $pw) => [
            'email' => $nutzer->email, 'pwtoken' => $token, 'password' => $pw, 'password_confirmation' => $pw,
        ];

        $this->from('/x')->post(route('password.updatewithtoken'), $daten(self::ELF))->assertSessionHasErrors('password');
        $this->assertSame($vorher, $this->hash($nutzer));

        // Gegenprobe: gleicher Token, 12 Zeichen.
        $this->from('/x')->post(route('password.updatewithtoken'), $daten(self::ZWOELF))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::ZWOELF, $this->hash($nutzer)));
    }

    // AK3
    public function test_ak3_ohne_config_wert_gilt_standard_acht(): void
    {
        $this->schluesselEntfernen();
        $nutzer = $this->nutzer();
        $vorher = $this->hash($nutzer);

        $this->aendern($nutzer, 'Abcde1!')->assertSessionHasErrors('password');
        $this->assertSame($vorher, $this->hash($nutzer));

        $this->aendern($nutzer, 'Abcdef1!')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Abcdef1!', $this->hash($nutzer)));
    }

    // AK4
    public function test_ak4_admin_anlegen_und_aendern_verlangen_konfigurierte_mindestlaenge(): void
    {
        config()->set('userauth.passwort.min_laenge', 12);
        $admin = $this->nutzer('admin@example.test');
        $ziel = $this->nutzer('ziel@example.test');
        $vorher = $this->hash($ziel);
        $felder = fn (string $pw, array $extra = []) => array_merge([
            'name' => 'Neuer Nutzer', 'email' => 'neu@example.test',
            'password' => $pw, 'password2' => $pw, 'role_id' => self::ROLLE_NORMAL,
        ], $extra);

        $this->actingAs($admin)->post('/users/store', $felder(self::ELF))->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'neu@example.test']);

        $this->actingAs($admin)->post('/users/update/'.$ziel->id, $felder(self::ELF, ['email' => 'ziel2@example.test']))
            ->assertSessionHasErrors('password');
        $this->assertSame($vorher, $this->hash($ziel));

        // Gegenprobe: mit 12 Zeichen wird angelegt.
        $this->actingAs($admin)->post('/users/store', $felder(self::ZWOELF))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'neu@example.test']);
    }

    // AK4
    public function test_ak4_selbstregistrierung_verlangt_konfigurierte_mindestlaenge(): void
    {
        config()->set('userauth.passwort.min_laenge', 12);

        $this->registrieren(['password' => self::ELF, 'password_confirmation' => self::ELF])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'kunde@example.test']);

        // Gegenprobe: mit 12 Zeichen wird das Konto angelegt.
        $this->registrieren(['password' => self::ZWOELF, 'password_confirmation' => self::ZWOELF])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'kunde@example.test']);
    }

    // AK5
    public function test_ak5_config_datei_enthaelt_passwort_min_laenge_mit_standard_acht_und_env(): void
    {
        $config = require __DIR__.'/../../src/Config/config.php';

        $this->assertSame(8, $config['passwort']['min_laenge'] ?? null);

        putenv('USERAUTH_PASSWORT_MIN_LAENGE=14');
        try {
            $mitEnv = require __DIR__.'/../../src/Config/config.php';
        } finally {
            putenv('USERAUTH_PASSWORT_MIN_LAENGE');
        }
        $this->assertEquals(14, $mitEnv['passwort']['min_laenge'] ?? null);
    }

    // AK5
    public function test_ak5_readme_nennt_einstellung_und_changelog_hat_eintrag_3_5_0(): void
    {
        $this->assertStringContainsString('min_laenge', (string) file_get_contents(__DIR__.'/../../README.md'));
        $this->assertStringContainsString('## 3.5.0', (string) file_get_contents(__DIR__.'/../../CHANGELOG.md'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function ungueltigeWerte(): array
    {
        return ['Text' => ['abc'], 'Kommazahl' => [12.5], 'null' => [0], 'negativ' => [-3], 'leer' => ['']];
    }

    // Randfall 1 (ungültiger Wert → 8)
    #[DataProvider('ungueltigeWerte')]
    public function test_randfall_ungueltiger_wert_faellt_auf_acht_zurueck(mixed $wert): void
    {
        config()->set('userauth.passwort.min_laenge', $wert);
        $nutzer = $this->nutzer();

        $this->aendern($nutzer, 'Abcde1!')->assertSessionHasErrors('password');
        $this->aendern($nutzer, 'Abcdef1!')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Abcdef1!', $this->hash($nutzer)));
    }

    // Randfall 2 (Meldung nennt die tatsächliche Mindestlänge)
    public function test_randfall_meldung_nennt_die_konfigurierte_mindestlaenge(): void
    {
        config()->set('userauth.passwort.min_laenge', 12);

        $this->aendern($this->nutzer(), self::ELF)->assertSessionHasErrors('password');
        $meldung = session('errors')->get('password')[0] ?? '';

        $this->assertStringContainsString('12', $meldung);
        $this->assertStringNotContainsString('8', $meldung);
    }
}
