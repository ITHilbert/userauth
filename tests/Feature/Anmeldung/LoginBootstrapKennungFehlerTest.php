<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Anmeldung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use ITHilbert\UserAuth\Tests\Support\AnmeldungPerHilfen;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regression #1324: Im Bootstrap-Theme hatte das Feld der Kennung zwei class-Attribute, `is-invalid` kam nie an.
 */
final class LoginBootstrapKennungFehlerTest extends TestCase
{
    use AnmeldungPerHilfen;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grundeinstellung();
        $this->benutzernameSpalteAnlegen();
        $this->emailIndexEntfernen();
        config()->set('userauth.theme', 'bootstrap');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function modi(): array
    {
        return ['email' => ['email'], 'benutzername' => ['benutzername'], 'beides' => ['beides']];
    }

    /** Das rohe `<input … name="<feld>" …>`-Tag der Kennung. */
    private function rohesTag(string $html, string $feld): string
    {
        $this->assertSame(1, preg_match('~<input\b[^>]*\bname="'.preg_quote($feld, '~').'"[^>]*>~', $html, $treffer), "Eingabefeld {$feld} fehlt im HTML");

        return $treffer[0];
    }

    // Bugfix #1324 (AK: Fehler am Feld der Kennung wird markiert)
    #[DataProvider('modi')]
    public function test_bootstrap_markiert_das_feld_der_kennung_nach_fehlgeschlagener_anmeldung_mit_einem_class_attribut(string $modus): void
    {
        $this->modus($modus);
        $feld = self::kennungsFeld($modus);
        $this->konto('anna.m', 'anna@example.test');

        // Gegenprobe: frische Seite, Bootstrap-Theme greift, kein is-invalid
        $frisch = $this->loginSeite();
        $tagFrisch = $this->rohesTag($frisch, $feld);
        $this->assertSame(1, preg_match_all('~\sclass=~', $tagFrisch), "Ohne Fehler genau ein class-Attribut: {$tagFrisch}");
        $klassen = preg_split('~\s+~', trim($this->eingabe($frisch, $feld)->getAttribute('class')));
        $this->assertContains('form-control', $klassen);
        $this->assertNotContains('is-invalid', $klassen);

        $this->anmelden($feld, 'unbekannt.x')->assertRedirect('/login')->assertSessionHasErrors($feld);

        $html = (string) $this->senden('get', '/login')->assertOk()->getContent();
        $tag = $this->rohesTag($html, $feld);
        $this->assertSame(1, preg_match_all('~\sclass=~', $tag), "Das Feld hat mehr als ein class-Attribut: {$tag}");

        $klassen = preg_split('~\s+~', trim($this->eingabe($html, $feld)->getAttribute('class')));
        $this->assertContains('is-invalid', $klassen, "is-invalid fehlt am Feld {$feld}: {$tag}");
        $this->assertContains('form-control', $klassen);
    }
}
