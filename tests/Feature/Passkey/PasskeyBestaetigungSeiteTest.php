<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verwaltungsseite `/passkey`: Hooks für die Passwortabfrage vor „Passkey hinzufügen“ (userauth passkey-bestaetigung,
 * #2225, AK8). Das Verhalten von `passkey.js` prüft `tests/js/passkey-bestaetigung.test.mjs`; diese Datei prüft, dass die
 * Seite die Hooks liefert, die das Skript braucht, und dass die URLs auf die echten Routen zeigen. Vorgaben: Das
 * Formular `[data-passkey-registrieren]` trägt `data-bestaetigen-url` und `data-bestaetigung="<id>"`; bei Benutzern mit
 * Passkey zusätzlich `data-bestaetigen-optionen-url` und `data-bestaetigen-assertion-url`. Das Element `#<id>` trägt
 * `data-testid="passkey-bestaetigung"`, ist anfangs `hidden`, liegt im Bereich `passkey-hinzufuegen` und enthält
 * `input[name=aktuelles_passwort]` (type password), die Elemente `passkey-bestaetigung-passwort` (Knopf),
 * `passkey-bestaetigung-meldung` und bei Benutzern mit Passkey den Knopf `passkey-bestaetigung-passkey`.
 */
final class PasskeyBestaetigungSeiteTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
    }

    /** @return array<string, array{0: string}> */
    public static function themes(): array
    {
        return ['tailwind' => ['tailwind'], 'bootstrap' => ['bootstrap']];
    }

    private function seite(string $theme): DOMXPath
    {
        config()->set('userauth.theme', $theme);
        config()->set('laravelkit.theme', $theme);
        $html = (string) $this->get(route('userauth.passkey.index'))->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    private function einzeln(DOMXPath $seite, string $xpath, string $was): DOMElement
    {
        $treffer = $seite->query($xpath);
        $this->assertSame(1, $treffer->length, "{$was}: erwartet genau ein Element ({$xpath}), gefunden {$treffer->length}.");
        $element = $treffer->item(0);
        $this->assertInstanceOf(DOMElement::class, $element);

        return $element;
    }

    // AK8
    #[DataProvider('themes')]
    public function test_ak8_seite_liefert_versteckte_passwortabfrage_und_bestaetigungs_url_ohne_passkey_knopf(string $theme): void
    {
        $this->actingAs($this->nutzer());

        $seite = $this->seite($theme);

        $formular = $this->einzeln($seite, '//*[@data-testid="passkey-hinzufuegen"]//form[@data-passkey-registrieren]', 'Formular');
        $this->assertSame(route('userauth.passkey.bestaetigen'), $formular->getAttribute('data-bestaetigen-url'));
        $id = $formular->getAttribute('data-bestaetigung');
        $this->assertNotSame('', $id, 'Das Formular verweist nicht auf die Passwortabfrage.');

        $dialog = $this->einzeln($seite, '//*[@data-testid="passkey-hinzufuegen"]//*[@data-testid="passkey-bestaetigung"]', 'Passwortabfrage');
        $this->assertSame($id, $dialog->getAttribute('id'));
        $this->assertTrue($dialog->hasAttribute('hidden'), 'Die Passwortabfrage ist ohne 403 sichtbar.');
        $feld = $this->einzeln($seite, '//*[@data-testid="passkey-bestaetigung"]//input[@name="aktuelles_passwort"]', 'Passwortfeld');
        $this->assertSame('password', $feld->getAttribute('type'));
        $this->einzeln($seite, '//*[@data-testid="passkey-bestaetigung"]//*[@data-testid="passkey-bestaetigung-passwort"]', 'Knopf Passwort');
        $this->einzeln($seite, '//*[@data-testid="passkey-bestaetigung"]//*[@data-testid="passkey-bestaetigung-meldung"]', 'Meldung');
        $this->assertSame(0, $seite->query('//*[@data-testid="passkey-bestaetigung-passkey"]')->length, 'Ohne Passkey gibt es keinen Knopf „Mit Passkey bestätigen“.');
    }

    // AK8
    #[DataProvider('themes')]
    public function test_ak8_mit_passkey_kommt_der_knopf_mit_den_urls_fuer_optionen_und_assertion_dazu(string $theme): void
    {
        [$nutzer] = $this->nutzerMitPasskey();
        $this->actingAs($nutzer);

        $seite = $this->seite($theme);

        $formular = $this->einzeln($seite, '//form[@data-passkey-registrieren]', 'Formular');
        $this->assertSame(route('userauth.passkey.bestaetigen.optionen'), $formular->getAttribute('data-bestaetigen-optionen-url'));
        $this->assertSame(route('userauth.passkey.bestaetigen.assertion'), $formular->getAttribute('data-bestaetigen-assertion-url'));
        $this->assertSame(route('userauth.passkey.bestaetigen'), $formular->getAttribute('data-bestaetigen-url'));
        $this->einzeln($seite, '//*[@data-testid="passkey-bestaetigung"]//*[@data-testid="passkey-bestaetigung-passkey"]', 'Knopf Passkey');
        $this->einzeln($seite, '//*[@data-testid="passkey-bestaetigung"]//*[@data-testid="passkey-bestaetigung-passwort"]', 'Knopf Passwort');
    }

    // Randfall 1 (Satz 2): Der feste Dialogtext nennt den Weg „Passwort vergessen“ (Server-Meldung allein wird nicht gesehen)
    #[DataProvider('themes')]
    public function test_randfall1_dialog_nennt_den_weg_passwort_vergessen_in_de_und_en(string $theme): void
    {
        $this->actingAs($this->nutzer());
        $erwartet = ['de' => ['Passwort vergessen', 'Forgot password'], 'en' => ['Forgot password', 'Passwort vergessen']];

        foreach ($erwartet as $sprache => [$soll, $fremd]) {
            app()->setLocale($sprache);
            $seite = $this->seite($theme);
            $dialog = $this->einzeln($seite, '//*[@data-testid="passkey-bestaetigung"]', "Dialog {$sprache}");

            $this->assertStringContainsString($soll, $dialog->textContent, "Der Dialog ({$sprache}) nennt den Weg „{$soll}“ nicht.");
            $this->assertStringNotContainsString($fremd, $dialog->textContent, "Der Dialog ({$sprache}) zeigt die falsche Sprache.");
        }
    }
}
