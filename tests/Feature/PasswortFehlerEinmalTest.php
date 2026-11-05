<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * userauth-admin-nachzug (Dashboard #660), AK2: Ein Validierungsfehler am Feld `password` steht auf
 * user.create und user.edit genau einmal im HTML (im Block von include.message), nicht zusätzlich am Feld.
 */
final class PasswortFehlerEinmalTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE = 3;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        config(['laravelkit.theme' => 'tailwind', 'userauth.password_policy.enabled' => false]);
        app()->setLocale('de');

        $jetzt = now();
        DB::table('roles')->insert(['id' => self::ROLLE, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        foreach (['user_create', 'user_edit'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            DB::table('role_permission')->insert(['role_id' => self::ROLLE, 'permission_id' => $i + 1]);
        }
        $this->admin = User::factory()->create(['role_id' => self::ROLLE, 'email' => 'admin@example.test']);
    }

    /**
     * @return array<string, mixed>
     */
    private function daten(string $email): array
    {
        return [
            'name' => 'Neu', 'email' => $email, 'role_id' => self::ROLLE,
            'password' => 'Gueltiges-Passwort-1!', 'password2' => 'Anderes-Passwort-2!',
        ];
    }

    // AK2
    public function test_fehler_am_passwortfeld_steht_auf_user_create_genau_einmal_im_html(): void
    {
        $formular = route('user.create');
        $this->actingAs($this->admin)->get($formular)->assertOk();

        $this->from($formular)->post(route('user.store'), $this->daten('neu@example.test'))->assertSessionHasErrors();
        $fehler = session('errors');
        $this->assertTrue($fehler->has('password'), 'Fehler liegt am Feld password');
        $meldung = $fehler->first('password');

        $html = $this->from($formular)->followingRedirects()->post(route('user.store'), $this->daten('neu@example.test'))->assertOk()->getContent();

        $this->assertStringContainsString(e($meldung), $html, 'Gegenprobe: die Meldung erscheint');
        $this->assertSame(1, substr_count($html, e($meldung)), 'Meldung genau einmal in der Seite');
    }

    // AK2
    public function test_fehler_am_passwortfeld_steht_auf_user_edit_genau_einmal_im_html(): void
    {
        $ziel = User::factory()->create(['role_id' => self::ROLLE, 'email' => 'ziel@example.test']);
        $formular = route('user.edit', $ziel->id);
        $this->actingAs($this->admin)->get($formular)->assertOk();

        $this->from($formular)->post(route('user.update', $ziel->id), $this->daten('ziel@example.test'))->assertSessionHasErrors();
        $fehler = session('errors');
        $this->assertTrue($fehler->has('password'), 'Fehler liegt am Feld password');
        $meldung = $fehler->first('password');

        $html = $this->from($formular)->followingRedirects()->post(route('user.update', $ziel->id), $this->daten('ziel@example.test'))->assertOk()->getContent();

        $this->assertStringContainsString(e($meldung), $html, 'Gegenprobe: die Meldung erscheint');
        $this->assertSame(1, substr_count($html, e($meldung)), 'Meldung genau einmal in der Seite');
    }

    // AK2
    public function test_die_meldung_steht_im_block_von_include_message_und_nicht_am_feld(): void
    {
        $formular = route('user.create');
        $this->actingAs($this->admin)->get($formular)->assertOk();

        $this->from($formular)->post(route('user.store'), $this->daten('neu@example.test'))->assertSessionHasErrors('password');
        $meldung = session('errors')->first('password');

        $html = $this->from($formular)->followingRedirects()->post(route('user.store'), $this->daten('neu@example.test'))->getContent();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xp = new DOMXPath($dom);

        $imBlock = 0;
        foreach ($xp->query('//div[contains(@class,"alert-danger")]//li') as $zeile) {
            $imBlock += trim($zeile->textContent) === $meldung ? 1 : 0;
        }
        $this->assertSame(1, $imBlock, 'Gegenprobe: im Block von include.message');

        $feld = $xp->query('//*[@id="password"]')->item(0);
        $this->assertNotNull($feld, 'Gegenprobe: Feld password gerendert');
        $zeile = $feld;
        while ($zeile !== null && ! str_contains((string) $zeile->getAttribute('class'), 'row')) {
            $zeile = $zeile->parentNode;
        }
        $this->assertNotNull($zeile, 'Gegenprobe: Zeile des Feldes gefunden');
        $this->assertStringNotContainsString($meldung, $zeile->textContent, 'nicht zusätzlich am Feld');
    }
}
