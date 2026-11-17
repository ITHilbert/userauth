<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * userauth-haertung AK25 (Dashboard #1496): Passwort ändern verlangt das aktuelle Passwort.
 */
final class PasswortAendernAktuellesPasswortTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    private const AKTUELLES_PASSWORT = 'Aktuelles-Passwort-1!';

    private const NEUES_PASSWORT = 'Neues-Passwort-2026!';

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        config()->set('userauth.password_policy.enabled', false);
        config()->set('userauth.two_factor_enabled', false);

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => self::ROLLE_NORMAL, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
    }

    private function nutzer(): User
    {
        return User::factory()->create([
            'role_id' => self::ROLLE_NORMAL,
            'email' => 'alt@example.test',
            'password' => Hash::make(self::AKTUELLES_PASSWORT),
        ]);
    }

    private function hash(User $nutzer): string
    {
        return (string) DB::table('users')->where('id', $nutzer->id)->value('password');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function aendern(User $nutzer, array $extra)
    {
        return $this->actingAs($nutzer)->from(route('password.edit'))->post(route('password.update'), array_merge([
            'password' => self::NEUES_PASSWORT,
            'password_confirmation' => self::NEUES_PASSWORT,
        ], $extra));
    }

    // AK25
    public function test_ak25_fehlendes_aktuelles_passwort_wird_am_feld_abgelehnt_und_hash_bleibt(): void
    {
        $nutzer = $this->nutzer();
        $vorher = $this->hash($nutzer);
        $this->assertTrue(Hash::check(self::AKTUELLES_PASSWORT, $vorher), 'Gegenprobe: Ausgangshash gehört zum aktuellen Passwort');

        $this->aendern($nutzer, [])->assertSessionHasErrors('aktuelles_passwort');

        $this->assertSame($vorher, $this->hash($nutzer));
    }

    // AK25
    public function test_ak25_falsches_aktuelles_passwort_wird_am_feld_abgelehnt_und_hash_bleibt(): void
    {
        $nutzer = $this->nutzer();
        $vorher = $this->hash($nutzer);
        $this->assertTrue(Hash::check(self::AKTUELLES_PASSWORT, $vorher), 'Gegenprobe: Ausgangshash gehört zum aktuellen Passwort');

        $this->aendern($nutzer, ['aktuelles_passwort' => 'Falsches-Passwort-9!'])->assertSessionHasErrors('aktuelles_passwort');

        $this->assertSame($vorher, $this->hash($nutzer));
    }

    // AK25
    public function test_ak25_aktuelles_passwort_als_liste_wird_am_feld_abgelehnt_ohne_500_und_hash_bleibt(): void
    {
        $nutzer = $this->nutzer();
        $vorher = $this->hash($nutzer);
        $this->assertTrue(Hash::check(self::AKTUELLES_PASSWORT, $vorher), 'Gegenprobe: Ausgangshash gehört zum aktuellen Passwort');

        $this->aendern($nutzer, ['aktuelles_passwort' => ['x']])
            ->assertSessionHasErrors('aktuelles_passwort');
        $this->assertSame($vorher, $this->hash($nutzer));

        $this->actingAs($nutzer)->postJson(route('password.update'), [
            'aktuelles_passwort' => ['x'],
            'password' => self::NEUES_PASSWORT,
            'password_confirmation' => self::NEUES_PASSWORT,
        ])->assertStatus(422)->assertJsonValidationErrors('aktuelles_passwort');
        $this->assertSame($vorher, $this->hash($nutzer));
    }

    // AK25
    public function test_ak25_richtiges_aktuelles_passwort_aendert_das_passwort(): void
    {
        $nutzer = $this->nutzer();

        $this->aendern($nutzer, ['aktuelles_passwort' => self::AKTUELLES_PASSWORT])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $this->hash($nutzer)));
    }

    // AK25
    public function test_ak25_formular_enthaelt_feld_aktuelles_passwort_mit_autocomplete(): void
    {
        $html = $this->actingAs($this->nutzer())->get(route('password.edit'))->assertOk()->getContent();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $felder = (new DOMXPath($dom))->query('//form[contains(@action, "password/update")]//input[@name="aktuelles_passwort"]');

        $this->assertSame(1, $felder->length, 'genau ein Feld aktuelles_passwort im Formular');
        $this->assertSame('current-password', $felder->item(0)->getAttribute('autocomplete'));
    }

    // AK25
    public function test_ak25_schema_fassung_enthaelt_aktuelles_passwort_vor_dem_neuen_passwort(): void
    {
        $schema = json_decode(
            (string) file_get_contents(__DIR__.'/../../src/Resources/schema/ui/password/edit.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertTrue($schema['dataSchema']['aktuelles_passwort']['required'] ?? false);

        $felder = array_map(fn (array $element) => $element['field'] ?? null, $schema['uiSchema']['elements']);
        $neu = array_search('password', $felder, true);
        $aktuell = array_search('aktuelles_passwort', $felder, true);

        $this->assertNotFalse($neu, 'Gegenprobe: Element für password vorhanden');
        $this->assertNotFalse($aktuell);
        $this->assertLessThan($neu, $aktuell);
        $this->assertSame('input-password', $schema['uiSchema']['elements'][$aktuell]['type']);
        $this->assertSame('current-password', $schema['uiSchema']['elements'][$aktuell]['autocomplete']);
    }
}
