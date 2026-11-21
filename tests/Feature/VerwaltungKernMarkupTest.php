<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ITHilbert\UserAuth\Tests\Support\VerwaltungSeitenHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verwaltungsseiten im Theme kern: nur kit-/userauth-Klassen, Formularzeilen, Zeilenbuttons (kern-umstellung T2).
 */
final class VerwaltungKernMarkupTest extends TestCase
{
    use RefreshDatabase;
    use VerwaltungSeitenHilfe;

    /** Zustandsklassen, die die Kit-Components selbst setzen (`is-active`, `is-invalid`). */
    private const ERLAUBT = ['d-none', 'data-table', 'is-active', 'is-invalid'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->verwaltungAufbauen('adminviews');
    }

    /**
     * @return list<string>
     */
    private function fremdeKlassen(DOMXPath $xp): array
    {
        $fremd = [];
        foreach ($xp->query('//body//*[@class]') as $el) {
            foreach (preg_split('/\s+/', trim($el->getAttribute('class'))) ?: [] as $klasse) {
                if ($klasse === '' || str_starts_with($klasse, 'kit-') || str_starts_with($klasse, 'userauth-') || in_array($klasse, self::ERLAUBT, true)) {
                    continue;
                }
                $fremd[] = $el->tagName.'.'.$klasse;
            }
        }

        return array_values(array_unique($fremd));
    }

    private function hatKlasse(string $klasse): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$klasse.' ")';
    }

    // ------------------------------------------------------------------ AK4

    // AK4
    #[DataProvider('verwaltungSeiten')]
    public function test_ak4_seite_traegt_nur_kit_und_userauth_klassen(string $seite): void
    {
        $xp = $this->verwaltungDom($this->verwaltungSeite($seite));

        // Gegenprobe: Aufbau greift (Kit-Card gerendert) und die Prüfung erkennt Bootstrap-Klassen
        $this->assertGreaterThan(0, $xp->query('//div['.$this->hatKlasse('kit-card').']')->length, 'Aufbau: Theme kern rendert kit-card');
        $probe = $this->verwaltungDom('<html><body><div class="btn kit-x col-md-6 userauth-y"></div></body></html>');
        $this->assertSame(['div.btn', 'div.col-md-6'], $this->fremdeKlassen($probe));

        $this->assertSame([], $this->fremdeKlassen($xp), 'Klassen außerhalb kit-/userauth-/d-none/data-table');
    }

    // ------------------------------------------------------------------ AK5

    /** @return array<string, array{0: string}> */
    public static function benutzerFormulare(): array
    {
        return ['user.create' => ['user.create'], 'user.edit' => ['user.edit']];
    }

    // AK5
    #[DataProvider('benutzerFormulare')]
    public function test_ak5_formularzeilen_und_felder_sind_kit_components(string $seite): void
    {
        foreach (['anrede', 'title', 'firstname', 'name', 'smallname', 'role', 'email', 'private_email', 'street', 'postcode', 'city', 'country', 'signature_rule', 'ustid', 'phone', 'phone2', 'mobile', 'fax', 'skype', 'hourly_rate', 'birthday', 'comment', 'image'] as $feld) {
            config(['userauth.user.'.$feld => true]);
        }
        $xp = $this->verwaltungDom($this->verwaltungSeite($seite));

        $zeilen = $xp->query('//div['.$this->hatKlasse('userauth-zeile').']');
        $this->assertGreaterThan(5, $zeilen->length, 'Formularzeilen als div.userauth-zeile');
        foreach ($zeilen as $zeile) {
            $this->assertSame(1, $xp->query('.//*['.$this->hatKlasse('userauth-zeile__label').']', $zeile)->length, 'Label je Zeile');
            $this->assertSame(1, $xp->query('.//*['.$this->hatKlasse('userauth-zeile__feld').']', $zeile)->length, 'Feld je Zeile');
        }

        $steuerelemente = $xp->query('//form//input[@name and @name!="" and not(@type="hidden") and not(@type="submit")]|//form//select[@name]|//form//textarea[@name]');
        $this->assertGreaterThan(10, $steuerelemente->length, 'Aufbau: Felder gerendert');
        foreach ($steuerelemente as $el) {
            $this->assertSame(1, $xp->query('ancestor::*['.$this->hatKlasse('userauth-zeile__feld').'][ancestor::div['.$this->hatKlasse('userauth-zeile').']]', $el)->length, 'Feld '.$el->getAttribute('name').' liegt in einer Zeile');
        }

        foreach (['kit-input-text', 'kit-input-email', 'kit-input-password', 'kit-input-date', 'kit-combobox', 'kit-textarea'] as $komponente) {
            $this->assertGreaterThan(0, $xp->query('//div['.$this->hatKlasse($komponente).']')->length, $komponente.' kommt auf '.$seite.' vor');
        }
    }

    // AK5
    #[DataProvider('benutzerFormulare')]
    public function test_ak5_passwort_knoepfe_sind_kit_button_mit_den_hooks_der_skripte(string $seite): void
    {
        config(['userauth.passwort_generator_url' => '/passwort/erzeugen']);
        $xp = $this->verwaltungDom($this->verwaltungSeite($seite));

        foreach (['data-passwort-erzeugen', 'data-passwort-kopieren'] as $haken) {
            $knoepfe = $xp->query('//button[@'.$haken.']');
            $this->assertSame(1, $knoepfe->length, 'Aufbau: Knopf mit '.$haken);
            $klassen = preg_split('/\s+/', $knoepfe->item(0)->getAttribute('class')) ?: [];
            $this->assertContains('kit-button', $klassen);
            $this->assertNotContains('btn', $klassen);
        }
    }

    // AK5
    public function test_ak5_haken_der_skripte_auf_der_rechteseite_und_kit_checkbox_in_der_rollenmaske(): void
    {
        $xp = $this->verwaltungDom($this->verwaltungSeite('permission.create'));
        $this->assertSame(1, $xp->query('//*[@id="group_permissions_show"]')->length);
        $this->assertSame(1, $xp->query('//*[@id="vorlage"]')->length);

        $rolle = $this->verwaltungDom($this->verwaltungSeite('role.edit'));
        $this->assertGreaterThan(0, $rolle->query('//div['.$this->hatKlasse('kit-checkbox').']')->length);
    }

    // ------------------------------------------------------------------ AK6

    private function gruppeUndRechte(int $id, string $name, array $arten): void
    {
        $jetzt = now();
        DB::table('permissions_groups')->insert(['id' => $id, 'group_name' => $name, 'group_display' => 'Gruppe '.$name, 'is_group' => 0, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        foreach ($arten as $art) {
            DB::table('permissions')->insert(['permission' => $name.'_'.$art, 'permission_display' => $name.' '.$art, 'group_id' => $id, 'crud' => $art, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }
    }

    /**
     * Aktionsspalte der Zeile als eigenes Dokument.
     */
    private function aktionen(string $liste, int $id, object $akteur): DOMXPath
    {
        $antwort = $this->actingAs($akteur)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route($liste))->assertOk();
        $zeilen = array_values(array_filter($antwort->json('data'), fn (array $z) => (int) $z['id'] === $id));
        $this->assertCount(1, $zeilen, 'Zeile '.$id.' der Liste '.$liste.' ist da');

        return $this->verwaltungDom('<html><body>'.$zeilen[0]['action'].'</body></html>');
    }

    private function kitButton(DOMXPath $xp, string $tag, string $variante): \DOMNodeList
    {
        return $xp->query('//'.$tag.'['.$this->hatKlasse('kit-button').'][@data-variant="'.$variante.'"]');
    }

    // AK6
    public function test_ak6_zeilenbuttons_mit_beiden_rechten_sind_kit_button(): void
    {
        $this->gruppeUndRechte(4, 'lager', ['create', 'read', 'edit', 'delete']);
        $faelle = [
            'user' => [$this->ziel->id, route('user.edit', $this->ziel->id)],
            'role' => [4, route('role.edit', 4)],
            'permission' => [4, route('permission.edit', 4)],
        ];

        foreach ($faelle as $liste => [$id, $editRoute]) {
            $xp = $this->aktionen($liste, $id, $this->admin);

            $edit = $this->kitButton($xp, 'a', 'edit');
            $this->assertSame(1, $edit->length, $liste.': Edit als a.kit-button');
            $this->assertSame($editRoute, $edit->item(0)->getAttribute('href'));
            $loeschen = $this->kitButton($xp, 'button', 'delete');
            $this->assertSame(1, $loeschen->length, $liste.': Löschen als button.kit-button');
            $this->assertSame((string) $id, $loeschen->item(0)->getAttribute('data-kit-delete'));
            $this->assertSame(0, $xp->query('//*['.$this->hatKlasse('btn').']')->length, $liste.': keine Klasse btn');
        }
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function einzelRechte(): array
    {
        return [
            'user nur edit' => ['user', 'edit', 'delete'],
            'user nur delete' => ['user', 'delete', 'edit'],
            'role nur edit' => ['role', 'edit', 'delete'],
            'role nur delete' => ['role', 'delete', 'edit'],
        ];
    }

    // AK6
    #[DataProvider('einzelRechte')]
    public function test_ak6_ohne_das_jeweilige_recht_fehlt_der_button(string $liste, string $hat, string $fehlt): void
    {
        $this->gruppeUndRechte(2, 'role', ['create', 'read', 'edit', 'delete']);
        $rechte = DB::table('permissions')->pluck('id', 'permission');
        foreach ([$liste.'_read', $liste.'_'.$hat] as $recht) {
            DB::table('role_permission')->insert(['role_id' => 4, 'permission_id' => $rechte[$recht]]);
        }
        $id = $liste === 'user' ? $this->ziel->id : 4;

        $xp = $this->aktionen($liste, $id, $this->ziel);

        $tag = fn (string $art) => $art === 'edit' ? 'a' : 'button';
        $this->assertSame(1, $this->kitButton($xp, $tag($hat), $hat)->length, 'Gegenprobe: Button zum vorhandenen Recht '.$hat);
        $this->assertSame(0, $this->kitButton($xp, $tag($fehlt), $fehlt)->length, 'Button zum fehlenden Recht '.$fehlt.' fehlt');
    }
}
