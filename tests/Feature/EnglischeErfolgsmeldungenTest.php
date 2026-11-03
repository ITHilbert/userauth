<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Englische Erfolgsmeldungen des Benutzer-CRUD (Dashboard #576, Bugfix).
 * Maßstab ist die Task-Abnahme; es gibt keinen Feature-Ordner, daher der Verweis auf #576 statt AK-Nummer.
 */
final class EnglischeErfolgsmeldungenTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    /** @var list<string> */
    private const ALTE_TEXTE = ['Add user successfull.', 'Add edit successfull.', 'Add delte successfull.'];

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        config()->set('userauth.password_policy.enabled', false);
        app()->setLocale('en');

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => self::ROLLE_NORMAL, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
        foreach (['user_create', 'user_edit', 'user_delete'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            DB::table('role_permission')->insert(['role_id' => self::ROLLE_NORMAL, 'permission_id' => $i + 1]);
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'admin@example.test']);
    }

    private function ziel(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'ziel@example.test']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formular(): array
    {
        return [
            'name' => 'Neuer Nutzer',
            'email' => 'neu@example.test',
            'password' => 'Neues-Passwort-2026!',
            'password2' => 'Neues-Passwort-2026!',
            'role_id' => self::ROLLE_NORMAL,
        ];
    }

    // #576
    public function test_anlegen_zeigt_englische_erfolgsmeldung(): void
    {
        $antwort = $this->actingAs($this->admin())->post(route('user.store'), $this->formular());

        $this->assertDatabaseHas('users', ['email' => 'neu@example.test']);
        $antwort->assertSessionHas('message', 'The user has been created.');
        $this->assertNotContains(session('message'), self::ALTE_TEXTE);
    }

    // #576
    public function test_aendern_zeigt_englische_erfolgsmeldung(): void
    {
        $ziel = $this->ziel();

        $antwort = $this->actingAs($this->admin())->post(route('user.update', $ziel->id), $this->formular());

        $this->assertDatabaseHas('users', ['id' => $ziel->id, 'email' => 'neu@example.test']);
        $antwort->assertSessionHas('message', 'The user has been updated.');
        $this->assertNotContains(session('message'), self::ALTE_TEXTE);
    }

    // #576
    public function test_loeschen_zeigt_englische_erfolgsmeldung(): void
    {
        $ziel = $this->ziel();

        $antwort = $this->actingAs($this->admin())->delete(route('user.delete', $ziel->id));

        $this->assertSoftDeleted('users', ['id' => $ziel->id]);
        $antwort->assertSessionHas('message', 'The user has been deleted.');
        $this->assertNotContains(session('message'), self::ALTE_TEXTE);
    }

    // #576
    public function test_sprachdatei_en_enthaelt_alte_texte_nicht_mehr(): void
    {
        $datei = (string) file_get_contents(__DIR__.'/../../src/Resources/lang/en/user.php');

        foreach (self::ALTE_TEXTE as $alt) {
            $this->assertStringNotContainsString($alt, $datei);
        }
        $this->assertStringContainsString('The user has been created.', $datei);
    }

    // #576
    public function test_readme_hat_upgrade_hinweis_zu_den_drei_meldungen(): void
    {
        $readme = (string) file_get_contents(__DIR__.'/../../README.md');
        $start = strpos($readme, '## Upgrade-Hinweise (nach 3.0.0');
        $this->assertNotFalse($start, 'Abschnitt Upgrade-Hinweise (nach 3.0.0) fehlt');
        $rest = substr($readme, $start + 5);
        $ende = strpos($rest, "\n## ");
        $abschnitt = $ende === false ? $rest : substr($rest, 0, $ende);

        foreach (['MsgAddSuccess', 'MsgEditSuccess', 'MsgDeleteSuccess'] as $schluessel) {
            $this->assertStringContainsString($schluessel, $abschnitt, "Upgrade-Hinweis nennt $schluessel nicht");
        }
    }
}
