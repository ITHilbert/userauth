<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use ITHilbert\UserAuth\Tests\Support\VerwaltungSeitenHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Markup des Lösch-Dialogs ohne Vue-Insel (kern-umstellung T3). Das Verhalten prüft tests/js/loeschen-dialog.test.mjs.
 */
final class LoeschDialogTest extends TestCase
{
    use RefreshDatabase;
    use VerwaltungSeitenHilfe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verwaltungAufbauen('adminviews');
    }

    /** @return array<string, array{0: string}> */
    public static function listen(): array
    {
        return ['user' => ['user'], 'role' => ['role'], 'permission' => ['permission']];
    }

    private function klasse(string $klasse): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$klasse.' ")';
    }

    /** Versteckt heißt: das Element oder ein Vorfahre trägt `hidden`, `display:none` oder `d-none`. */
    private function versteckt(\DOMElement $el): bool
    {
        for ($x = $el; $x instanceof \DOMElement; $x = $x->parentNode) {
            if ($this->selbstVersteckt($x)) {
                return true;
            }
        }

        return false;
    }

    private function selbstVersteckt(\DOMElement $el): bool
    {
        return $el->hasAttribute('hidden') || str_contains(str_replace(' ', '', $el->getAttribute('style')), 'display:none') || in_array('d-none', preg_split('/\s+/', $el->getAttribute('class')) ?: [], true);
    }

    // AK7
    #[DataProvider('listen')]
    public function test_ak7_liste_enthaelt_den_dialog_aus_blade_und_das_formular(string $liste): void
    {
        $html = $this->verwaltungSeite($liste);
        $xp = $this->verwaltungDom($html);

        $overlay = $xp->query('//div['.$this->klasse('kit-dialog__overlay').']');
        $this->assertSame(1, $overlay->length, 'Overlay');
        $this->assertTrue($this->versteckt($overlay->item(0)), 'Overlay ist versteckt');

        $inhalt = $xp->query('//div['.$this->klasse('kit-dialog__content').'][@role="dialog"][@aria-modal="true"]');
        $this->assertSame(1, $inhalt->length, 'Dialog-Inhalt mit role und aria-modal');
        $this->assertTrue($this->versteckt($inhalt->item(0)), 'Dialog-Inhalt ist versteckt');
        $titel = $xp->query('.//*['.$this->klasse('kit-dialog__title').']', $inhalt->item(0));
        $this->assertSame(1, $titel->length, 'Titel');
        $this->assertNotSame('', $titel->item(0)->getAttribute('id'));
        $this->assertSame($titel->item(0)->getAttribute('id'), $inhalt->item(0)->getAttribute('aria-labelledby'));
        $this->assertSame(2, $xp->query('.//button['.$this->klasse('kit-button').']', $inhalt->item(0))->length, 'Knöpfe Abbrechen und Löschen');

        $form = $xp->query('//form[@id="formDelete"]');
        $this->assertSame(1, $form->length);
        $this->assertSame('post', strtolower($form->item(0)->getAttribute('method')));
        $this->assertSame('DELETE', $xp->query('.//input[@name="_method"]', $form->item(0))->item(0)?->getAttribute('value'));
        $this->assertNotSame('', (string) $xp->query('.//input[@name="_token"]', $form->item(0))->item(0)?->getAttribute('value'));

        $this->assertSame(0, $xp->query('//*[@data-vue]')->length, 'keine Vue-Insel');
    }

    // AK7 und Randfall 5
    public function test_ak7_titel_und_text_kommen_aus_den_parametern_und_html_erscheint_als_text(): void
    {
        $titel = '<b>{{ 7*7 }}</b> Titel';
        $text = 'Wirklich? <i>{{ 7*7 }}</i>';

        $html = Blade::render("@include('userauth::partials.loeschen', ['titel' => \$titel, 'body' => \$body, 'route' => \$route])", [
            'titel' => $titel, 'body' => $text, 'route' => route('user.delete', 0),
        ]);
        $xp = $this->verwaltungDom('<html><body>'.$html.'</body></html>');

        $inhalt = $xp->query('//div['.$this->klasse('kit-dialog__content').']');
        $this->assertSame(1, $inhalt->length, 'Aufbau: Dialog gerendert');
        $this->assertSame($titel, trim($xp->query('.//*['.$this->klasse('kit-dialog__title').']', $inhalt->item(0))->item(0)?->textContent ?? ''), 'Titel aus titel, als Text');
        $this->assertStringContainsString($text, $inhalt->item(0)->textContent, 'Text aus body, als Text');
        $this->assertSame(0, $xp->query('//b|//i')->length, 'HTML wird nicht ausgeführt');
        $this->assertStringNotContainsString('49', $inhalt->item(0)->textContent, 'Ausdruck wird nicht ausgewertet');
    }
}
