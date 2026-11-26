<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Paketdateien der Passkey-Bestätigung (userauth passkey-bestaetigung, #2225): README, CHANGELOG, Übersetzung der
 * 403-Meldung und der Konfigurationswert `bestaetigung_minuten` (Standard und Rückfall).
 */
final class PasskeyBestaetigungDokuTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
        $this->passkeyAutoBestaetigen = false;
    }

    protected function tearDown(): void
    {
        $this->passkeyAbbauen();

        parent::tearDown();
    }

    private function datei(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/'.$name);
    }

    /** README-Zeile der Umgebungsvariable (Tabellenzeile oder Absatz, in dem sie steht). */
    private function readmeUmgebung(): string
    {
        $readme = $this->datei('README.md');
        $this->assertSame(1, preg_match('/^[^\n]*USERAUTH_PASSKEY_BESTAETIGUNG_MINUTEN[^\n]*$/m', $readme, $zeile), 'Die README nennt USERAUTH_PASSKEY_BESTAETIGUNG_MINUTEN nicht.');

        return $zeile[0];
    }

    // AK12
    public function test_ak12_readme_nennt_die_variable_mit_standard_10_und_die_bestaetigung_per_passwort_oder_passkey(): void
    {
        $zeile = $this->readmeUmgebung();

        $this->assertStringContainsString('userauth.passkey.bestaetigung_minuten', $zeile);
        $this->assertMatchesRegularExpression('/(?<!\d)10(?!\d)/', $zeile, 'Der Standard 10 steht nicht in der README-Zeile.');
        $this->assertMatchesRegularExpression('/Passwort[^\n]*Passkey|Passkey[^\n]*Passwort/u', $this->datei('README.md'));
        $this->assertMatchesRegularExpression('/bestätig/iu', $this->datei('README.md'));
    }

    // AK12
    public function test_ak12_changelog_hat_einen_eintrag_4_4_0_ueber_der_vorgaengerversion(): void
    {
        $changelog = $this->datei('CHANGELOG.md');

        $this->assertSame(1, preg_match('/^## 4\.4\.0\b/m', $changelog, $treffer, PREG_OFFSET_CAPTURE), 'Der CHANGELOG hat keinen Eintrag 4.4.0.');
        $this->assertSame(1, preg_match('/^## 4\.3\.3\b/m', $changelog, $alt, PREG_OFFSET_CAPTURE));
        $this->assertLessThan($alt[0][1], $treffer[0][1], 'Der Eintrag 4.4.0 steht nicht über 4.3.3.');
        $eintrag = substr($changelog, $treffer[0][1], $alt[0][1] - $treffer[0][1]);
        $this->assertStringContainsString('USERAUTH_PASSKEY_BESTAETIGUNG_MINUTEN', $eintrag);
    }

    // AK1 (Übersetzung der 403-Meldung)
    public function test_ak1_meldung_der_403_antwort_kommt_aus_der_uebersetzung_in_de_und_en(): void
    {
        $this->actingAs($this->nutzer());
        $meldungen = [];

        foreach (['de', 'en'] as $sprache) {
            app()->setLocale($sprache);
            $antwort = $this->postJson(route('userauth.passkey.register.options'))->assertForbidden();
            $this->assertTrue($antwort->json('bestaetigung_erforderlich'));
            $meldungen[$sprache] = (string) $antwort->json('message');
            $this->assertNotSame('', trim($meldungen[$sprache]));
            $this->assertStringNotContainsString('userauth::', $meldungen[$sprache], 'Der Übersetzungsschlüssel kommt unübersetzt an.');
        }

        $this->assertNotSame($meldungen['de'], $meldungen['en']);
    }

    // AK2 (Konfiguration: Standard 10)
    public function test_ak2_ohne_konfigurationswert_gilt_der_standard_10_minuten(): void
    {
        $this->assertEquals(10, config('userauth.passkey.bestaetigung_minuten'), 'Die Paket-Konfiguration hat keinen Standard 10.');
        $this->fristPruefen(10);
    }

    /** @return array<string, array{0: mixed}> */
    public static function ungueltigeWerte(): array
    {
        return ['null' => [null], 'leer' => [''], 'null_minuten' => [0], 'negativ' => [-5], 'text' => ['abc'], 'null_als_text' => ['0'], 'gebrochen_unter_1' => [0.5]];
    }

    // AK3 (Werte unter 1 oder nicht numerisch wirken wie 10)
    #[DataProvider('ungueltigeWerte')]
    public function test_ak3_werte_unter_1_oder_nicht_numerisch_wirken_wie_10(mixed $wert): void
    {
        config()->set('userauth.passkey.bestaetigung_minuten', $wert);

        $this->fristPruefen(10);
    }

    /** Bestätigt bei festem Zeitpunkt; nach `$minuten` gilt sie noch, eine Sekunde später nicht mehr. */
    private function fristPruefen(int $minuten): void
    {
        Carbon::setTestNow('2031-03-14 10:00:00');
        $this->actingAs($this->nutzer());
        $this->postJson(route('userauth.passkey.bestaetigen'), ['aktuelles_passwort' => 'password'])->assertNoContent();

        Carbon::setTestNow(Carbon::parse('2031-03-14 10:00:00')->addMinutes($minuten));
        $this->postJson(route('userauth.passkey.register.options'))->assertOk();
        Carbon::setTestNow(Carbon::parse('2031-03-14 10:00:00')->addMinutes($minuten)->addSecond());
        $this->postJson(route('userauth.passkey.register.options'))->assertForbidden();
    }

    // AK3 (Gegenprobe: ein gesetzter gültiger Wert gilt)
    public function test_ak3_gesetzter_gueltiger_wert_gilt_statt_des_standards(): void
    {
        config()->set('userauth.passkey.bestaetigung_minuten', '2');

        $this->fristPruefen(2);
    }
}
