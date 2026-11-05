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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Button „Passwort erzeugen“ in der Benutzerverwaltung (userauth-passwort-erzeugen, Dashboard #635) — Serverseite.
 * Die Browserlogik prüft tests/js/passwort-erzeugen.test.mjs.
 *
 * Schnittstelle des Markups (Datenattribute, die das Skript findet):
 *  - `<button type="button" data-passwort-erzeugen="<Adresse>" data-fehler="<Text>">` (Adresse escaped im Attribut)
 *  - `<button type="button" data-passwort-kopieren data-kopiert="<Text>">`
 *  - ein Element `data-passwort-hinweis` (Meldungen), alles in der Zeile des Feldes `password`
 *  - Skript `vendor/userauth/js/passwort-erzeugen.js` im Stack `userauth-scripts`, nur mit Adresse
 *  - Sprachschlüssel `userauth::user.password_generate|password_copy|password_copied|password_generate_failed` (de und en)
 */
final class PasswortErzeugenTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    private const ADRESSE = '/passwort/erzeugen';

    private const SCHLUESSEL = ['password_generate', 'password_copy', 'password_copied', 'password_generate_failed'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        View::getFinder()->prependLocation(__DIR__.'/../Fixtures/skripteviews/stack');
        config(['laravelkit.theme' => 'tailwind', 'userauth.password_policy.enabled' => false]);

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
        $this->admin = User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'admin@example.test']);
    }

    // ---------------------------------------------------------------- Datenanbieter

    /** @return array<string, array{0: string}> */
    public static function seiten(): array
    {
        return ['user/create' => ['create'], 'user/edit' => ['edit']];
    }

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function seitenMitLeererAdresse(): array
    {
        $faelle = [];
        foreach (self::seiten() as $seite => [$art]) {
            foreach (['null' => null, 'leerer Text' => '', 'nur Leerraum' => "  \t\n "] as $name => $wert) {
                $faelle[$seite.', '.$name] = [$art, $wert];
            }
        }

        return $faelle;
    }

    // ---------------------------------------------------------------- Hilfen

    private function html(string $art): string
    {
        $url = $art === 'create' ? route('user.create') : route('user.edit', User::factory()->create(['role_id' => self::ROLLE_NORMAL])->id);

        return $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($dom);
    }

    /** Aufbau greift: Formular mit den Feldern password und password2 ist gerendert. */
    private function assertFormularGerendert(DOMXPath $xp): void
    {
        $this->assertSame(1, $xp->query('//input[@name="password"]')->length, 'Aufbau: Feld password');
        $this->assertSame(1, $xp->query('//input[@name="password2"]')->length, 'Aufbau: Feld password2');
    }

    private function mitAdresse(string $art, string $adresse = self::ADRESSE): string
    {
        config(['userauth.passwort_generator_url' => $adresse]);

        return $this->html($art);
    }

    // ---------------------------------------------------------------- AK1

    // AK1
    public function test_ak1_config_schluessel_ist_per_merge_config_vorhanden_und_standard_leer(): void
    {
        $this->assertTrue(array_key_exists('passwort_generator_url', config('userauth')), 'Schlüssel steht in der Paket-Config');
        $this->assertNull(config('userauth.passwort_generator_url'));
    }

    // AK1
    public function test_ak1_config_liest_die_umgebungsvariable(): void
    {
        $name = 'USERAUTH_PASSWORT_GENERATOR_URL';
        $paket = __DIR__.'/../../src/Config/config.php';

        try {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
            $this->assertNull((require $paket)['passwort_generator_url'], 'Gegenprobe: ohne Variable leer');

            $_SERVER[$name] = $_ENV[$name] = 'https://gen.example.test/pw';
            putenv($name.'=https://gen.example.test/pw');
            $this->assertSame('https://gen.example.test/pw', (require $paket)['passwort_generator_url']);
        } finally {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        }
    }

    // ---------------------------------------------------------------- AK2

    // AK2
    #[DataProvider('seitenMitLeererAdresse')]
    public function test_ak2_ohne_adresse_kein_button_kein_kopieren_kein_skript(string $art, ?string $adresse): void
    {
        // Gegenprobe: mit Adresse sind Buttons und Skript da, Aufbau greift.
        $mit = $this->mitAdresse($art);
        $this->assertSame(1, $this->xpath($mit)->query('//button[@data-passwort-erzeugen]')->length, 'Gegenprobe: Button mit Adresse');
        $this->assertStringContainsString('vendor/userauth/js/passwort-erzeugen.js', $mit, 'Gegenprobe: Skript mit Adresse');

        config(['userauth.passwort_generator_url' => $adresse]);
        $html = $this->html($art);
        $xp = $this->xpath($html);

        $this->assertFormularGerendert($xp);
        $this->assertSame(0, $xp->query('//*[@data-passwort-erzeugen]')->length, 'kein Button „Passwort erzeugen“');
        $this->assertSame(0, $xp->query('//*[@data-passwort-kopieren]')->length, 'kein Button „Kopieren“');
        $this->assertSame(0, $xp->query('//*[@data-passwort-hinweis]')->length, 'kein Hinweis-Element');
        // einzelwort: der Dateiname passwort-erzeugen steht auf der Seite nur im Skript dieses Features (Gegenprobe oben zeigt ihn mit Adresse)
        $this->assertStringNotContainsString('passwort-erzeugen', $html, 'kein Skript dafür');
    }

    // AK2
    #[DataProvider('seitenMitLeererAdresse')]
    public function test_ak2_felder_bleiben_ohne_adresse_wie_heute(string $art, ?string $adresse): void
    {
        config(['userauth.passwort_generator_url' => $adresse]);
        $xp = $this->xpath($this->html($art));

        foreach (['password', 'password2'] as $name) {
            $feld = $xp->query('//input[@name="'.$name.'"]');
            $this->assertSame(1, $feld->length, 'Aufbau: Feld '.$name);
            $this->assertSame('new-password', $feld->item(0)->getAttribute('autocomplete'));
            $this->assertSame('', $feld->item(0)->getAttribute('value'));
        }
    }

    // ---------------------------------------------------------------- AK3

    // AK3
    #[DataProvider('seiten')]
    public function test_ak3_buttons_sind_type_button_in_der_zeile_des_feldes_password(string $art): void
    {
        $xp = $this->xpath($this->mitAdresse($art));
        $this->assertFormularGerendert($xp);

        $zeile = '//input[@name="password"]/ancestor::div[contains(@class,"form-group")][1]';
        foreach (['data-passwort-erzeugen', 'data-passwort-kopieren'] as $attribut) {
            $alle = $xp->query('//button['.'@'.$attribut.']');
            $this->assertSame(1, $alle->length, 'genau ein Button '.$attribut);
            $this->assertSame('button', $alle->item(0)->getAttribute('type'), 'sendet das Formular nicht ab');
            $this->assertSame(1, $xp->query($zeile.'//button[@'.$attribut.']')->length, 'beim Feld password');
        }
        $this->assertSame(1, $xp->query($zeile.'//*[@data-passwort-hinweis]')->length, 'Hinweis-Element beim Feld password');
        $this->assertSame(0, $xp->query('//input[@name="password2"]/ancestor::div[contains(@class,"form-group")][1]//button[@data-passwort-erzeugen or @data-passwort-kopieren]')->length, 'nicht beim Feld password2');
    }

    // AK3
    #[DataProvider('seiten')]
    public function test_ak3_adresse_steht_escaped_im_attribut(string $art): void
    {
        $adresse = 'https://gen.example.test/pw?a=1&b="x"><script>alert(1)</script>\'';
        $html = $this->mitAdresse($art, $adresse);
        $xp = $this->xpath($html);

        $button = $xp->query('//button[@data-passwort-erzeugen]');
        $this->assertSame(1, $button->length, 'Aufbau: Button da');
        $this->assertSame($adresse, $button->item(0)->getAttribute('data-passwort-erzeugen'), 'Attribut hält die Adresse unverändert');
        $this->assertStringNotContainsString('<script>alert(1)', $html, 'Adresse nicht roh im HTML');
    }

    // AK3
    #[DataProvider('seiten')]
    public function test_ak3_kein_inline_skript_mit_der_adresse(string $art): void
    {
        $html = $this->mitAdresse($art, 'https://gen.example.test/einmalig');
        $this->assertSame(1, $this->xpath($html)->query('//button[@data-passwort-erzeugen]')->length, 'Aufbau: Button da');

        preg_match_all('#<script([^>]*)>(.*?)</script>#is', $html, $treffer, PREG_SET_ORDER);
        $this->assertNotEmpty($treffer, 'Aufbau: Skripte der Seite gefunden');
        foreach ($treffer as [, , $inhalt]) {
            $this->assertStringNotContainsString('gen.example.test', $inhalt, 'Adresse steht in keinem Inline-Skript');
        }
        $this->assertSame(1, substr_count($html, 'gen.example.test/einmalig'), 'Adresse steht nur im Attribut');
    }

    // AK3
    #[DataProvider('seiten')]
    public function test_ak3_skript_steht_genau_einmal_ueber_den_skript_weg_der_admin_views(string $art): void
    {
        $html = $this->mitAdresse($art);

        $this->assertSame(1, substr_count($html, 'vendor/userauth/js/passwort-erzeugen.js'));
        $this->assertMatchesRegularExpression('#<script[^>]*\bdefer\b[^>]*vendor/userauth/js/passwort-erzeugen\.js|<script[^>]*vendor/userauth/js/passwort-erzeugen\.js[^>]*\bdefer\b#i', $html, 'wie die übrigen Admin-Skripte mit defer');
    }

    // AK3
    public function test_ak3_sprachschluessel_in_de_und_en_vorhanden_und_unterschiedlich(): void
    {
        foreach (self::SCHLUESSEL as $schluessel) {
            $de = trans('userauth::user.'.$schluessel, [], 'de');
            $en = trans('userauth::user.'.$schluessel, [], 'en');

            $this->assertNotSame('userauth::user.'.$schluessel, $de, $schluessel.' fehlt in de');
            $this->assertNotSame('userauth::user.'.$schluessel, $en, $schluessel.' fehlt in en');
            $this->assertNotSame('', trim($de));
            $this->assertNotSame($de, $en, $schluessel.': en ist übersetzt');
        }
    }

    // AK3
    #[DataProvider('seiten')]
    public function test_ak3_texte_kommen_aus_den_sprachdateien(string $art): void
    {
        $texte = ['password_generate' => 'ERZEUGEN-QUELLE', 'password_copy' => 'KOPIEREN-QUELLE', 'password_copied' => 'KOPIERT-QUELLE', 'password_generate_failed' => 'FEHLER-QUELLE'];
        foreach ($texte as $schluessel => $text) {
            app('translator')->addLines(['user.'.$schluessel => $text], app()->getLocale(), 'userauth');
        }
        $xp = $this->xpath($this->mitAdresse($art));

        $erzeugen = $xp->query('//button[@data-passwort-erzeugen]')->item(0);
        $kopieren = $xp->query('//button[@data-passwort-kopieren]')->item(0);
        $this->assertNotNull($erzeugen, 'Aufbau: Button da');
        $this->assertStringContainsString('ERZEUGEN-QUELLE', $erzeugen->textContent);
        $this->assertSame('FEHLER-QUELLE', $erzeugen->getAttribute('data-fehler'));
        $this->assertStringContainsString('KOPIEREN-QUELLE', $kopieren->textContent);
        $this->assertSame('KOPIERT-QUELLE', $kopieren->getAttribute('data-kopiert'));
    }

    // AK3
    public function test_ak3_texte_folgen_der_sprache(): void
    {
        app()->setLocale('en');
        $xp = $this->xpath($this->mitAdresse('create'));

        $erzeugen = $xp->query('//button[@data-passwort-erzeugen]')->item(0);
        $this->assertNotNull($erzeugen, 'Aufbau: Button da');
        $this->assertStringContainsString(trans('userauth::user.password_generate', [], 'en'), $erzeugen->textContent);
        $this->assertSame(trans('userauth::user.password_generate_failed', [], 'en'), $erzeugen->getAttribute('data-fehler'));
        $this->assertNotSame(trans('userauth::user.password_generate_failed', [], 'de'), $erzeugen->getAttribute('data-fehler'));
    }

    // ---------------------------------------------------------------- AK7

    // AK7
    #[DataProvider('seiten')]
    public function test_ak7_felder_sind_ohne_klick_leer_auch_mit_adresse(string $art): void
    {
        $xp = $this->xpath($this->mitAdresse($art));

        $this->assertSame(1, $xp->query('//button[@data-passwort-erzeugen]')->length, 'Aufbau: Button da');
        foreach (['password', 'password2'] as $name) {
            $this->assertSame('', $xp->query('//input[@name="'.$name.'"]')->item(0)->getAttribute('value'));
        }
    }

    // AK7
    public function test_ak7_bearbeiten_mit_leerem_passwort_laesst_passwort_unveraendert(): void
    {
        config(['userauth.passwort_generator_url' => self::ADRESSE]);
        $ziel = User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'alt@example.test', 'name' => 'Alter Name', 'password' => Hash::make('Aktuelles-Passwort-1!')]);
        $hashVorher = DB::table('users')->where('id', $ziel->id)->value('password');

        $this->actingAs($this->admin)->post('/users/update/'.$ziel->id, [
            'name' => 'Neuer Name', 'email' => 'neu-alt@example.test', 'password' => '', 'password2' => '', 'role_id' => self::ROLLE_NORMAL,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Neuer Name', DB::table('users')->where('id', $ziel->id)->value('name'), 'Gegenprobe: Speichern greift');
        $this->assertSame($hashVorher, DB::table('users')->where('id', $ziel->id)->value('password'));
    }

    // AK7
    public function test_ak7_bearbeiten_ohne_passwortfelder_im_request_laesst_passwort_unveraendert(): void
    {
        config(['userauth.passwort_generator_url' => self::ADRESSE]);
        $ziel = User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'alt@example.test', 'name' => 'Alter Name', 'password' => Hash::make('Aktuelles-Passwort-1!')]);
        $hashVorher = DB::table('users')->where('id', $ziel->id)->value('password');

        $this->actingAs($this->admin)->post('/users/update/'.$ziel->id, [
            'name' => 'Neuer Name', 'email' => 'neu-alt@example.test', 'role_id' => self::ROLLE_NORMAL,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Neuer Name', DB::table('users')->where('id', $ziel->id)->value('name'), 'Gegenprobe: Speichern greift');
        $this->assertSame($hashVorher, DB::table('users')->where('id', $ziel->id)->value('password'));
    }

    // AK7
    public function test_ak7_erzeugtes_passwort_wird_mit_dem_formular_gespeichert(): void
    {
        config(['userauth.passwort_generator_url' => self::ADRESSE]);
        $passwort = 'Erzeugt-Passwort-2026!';

        $this->actingAs($this->admin)->post('/users/store', [
            'name' => 'Neuer Nutzer', 'email' => 'neu@example.test', 'password' => $passwort, 'password2' => $passwort, 'role_id' => self::ROLLE_NORMAL,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check($passwort, (string) DB::table('users')->where('email', 'neu@example.test')->value('password')));
    }

    // AK7
    public function test_ak7_validierung_gilt_unveraendert_zu_schwaches_passwort_wird_abgelehnt(): void
    {
        config(['userauth.passwort_generator_url' => self::ADRESSE]);
        $vorher = DB::table('users')->count();

        // Gegenprobe: ein gültiges Passwort wird angenommen, der Aufbau greift.
        $this->actingAs($this->admin)->post('/users/store', [
            'name' => 'Gut', 'email' => 'gut@example.test', 'password' => 'Gueltiges-Passwort-1!', 'password2' => 'Gueltiges-Passwort-1!', 'role_id' => self::ROLLE_NORMAL,
        ])->assertSessionHasNoErrors();
        $this->assertSame($vorher + 1, DB::table('users')->count());

        $this->actingAs($this->admin)->post('/users/store', [
            'name' => 'Schwach', 'email' => 'schwach@example.test', 'password' => 'Abc1234', 'password2' => 'Abc1234', 'role_id' => self::ROLLE_NORMAL,
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'schwach@example.test']);
    }

    // AK7
    public function test_ak7_passwortrichtlinie_historie_gilt_unveraendert(): void
    {
        config(['userauth.passwort_generator_url' => self::ADRESSE, 'userauth.password_policy.enabled' => true, 'userauth.password_policy.prevent_reuse_last_passwords' => 3]);
        $frueher = 'Fruehere-Passwort-2!';
        $ziel = User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'alt@example.test', 'password' => Hash::make('Aktuelles-Passwort-1!'), 'password_changed_at' => now()->subDays(10)]);
        DB::table('user_password_histories')->insert(['user_id' => $ziel->id, 'password' => Hash::make($frueher), 'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20)]);
        $hashVorher = DB::table('users')->where('id', $ziel->id)->value('password');

        $this->actingAs($this->admin)->post('/users/update/'.$ziel->id, [
            'name' => 'Neuer Name', 'email' => 'neu-alt@example.test', 'password' => 'Neues-Passwort-2026!', 'password2' => 'Neues-Passwort-2026!', 'role_id' => self::ROLLE_NORMAL,
        ])->assertSessionHasNoErrors();
        $this->assertNotSame($hashVorher, DB::table('users')->where('id', $ziel->id)->value('password'), 'Gegenprobe: neues Passwort gesetzt');

        $ziel2 = User::factory()->create(['role_id' => self::ROLLE_NORMAL, 'email' => 'alt2@example.test', 'password' => Hash::make('Aktuelles-Passwort-1!'), 'password_changed_at' => now()->subDays(10)]);
        DB::table('user_password_histories')->insert(['user_id' => $ziel2->id, 'password' => Hash::make($frueher), 'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20)]);
        $this->actingAs($this->admin)->post('/users/update/'.$ziel2->id, [
            'name' => 'Neuer Name', 'email' => 'neu-alt2@example.test', 'password' => $frueher, 'password2' => $frueher, 'role_id' => self::ROLLE_NORMAL,
        ])->assertSessionHasErrors(['password' => trans('userauth::password.bereits_verwendet')]);
    }

    // ---------------------------------------------------------------- AK8

    // AK8
    public function test_ak8_readme_beschreibt_die_option(): void
    {
        $readme = (string) file_get_contents(__DIR__.'/../../README.md');

        $this->assertSame(1, preg_match('/^#{2,4} .*Passwort.*erzeug.*$/mi', $readme), 'eigener Abschnitt zur Option');
        $abschnitt = $this->abschnittZu($readme, '/^#{2,4} .*Passwort.*erzeug.*$/mi');
        $this->assertStringContainsString('USERAUTH_PASSWORT_GENERATOR_URL', $abschnitt);
        $this->assertStringContainsString('"passwort"', $abschnitt, 'Antwortformat');
        $this->assertStringContainsString('/passwort/erzeugen', $abschnitt, 'Beispiel deploy');
        $this->assertMatchesRegularExpression('/Passwortrichtlinie/u', $abschnitt, 'Hinweis auf die Passwortrichtlinie');
    }

    // AK8
    public function test_ak8_upgrade_hinweis_nennt_asset_und_unveraendertes_verhalten_ohne_option(): void
    {
        $readme = (string) file_get_contents(__DIR__.'/../../README.md');
        $this->assertSame(1, preg_match('/^## Upgrade-Hinweise \(nach 3\.0\.0, unver/mi', $readme, $treffer), 'Aufbau: Upgrade-Abschnitt gefunden');

        $upgrade = $this->abschnittZu($readme, '/^## Upgrade-Hinweise \(nach 3\.0\.0, unver.*$/mi');
        $this->assertStringContainsString('passwort-erzeugen', $upgrade, 'Upgrade-Hinweis nennt das neue Asset');
        $this->assertStringContainsString('USERAUTH_PASSWORT_GENERATOR_URL', $upgrade);
    }

    /** Text ab der Überschrift bis zur nächsten Überschrift gleicher oder höherer Ebene. */
    private function abschnittZu(string $readme, string $muster): string
    {
        preg_match($muster, $readme, $m, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($m, 'Überschrift gefunden');
        $start = $m[0][1];
        $ebene = strlen((string) strstr($m[0][0], ' ', true));
        $rest = substr($readme, $start + strlen($m[0][0]));
        if (preg_match('/^#{1,'.$ebene.'} /m', $rest, $ende, PREG_OFFSET_CAPTURE)) {
            $rest = substr($rest, 0, $ende[0][1]);
        }

        return $rest;
    }
}
