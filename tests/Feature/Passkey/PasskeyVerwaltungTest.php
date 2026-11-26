<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Passkey;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ITHilbert\UserAuth\Tests\Support\PasskeyTestHilfe;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verwaltungsseite der eigenen Passkeys und Löschen (AK8, AK11).
 *
 * Vorgaben an die Seite: je Passkey ein Element `data-testid="passkey-zeile"` mit Name, angelegt am und zuletzt
 * benutzt; der Bereich „Passkey hinzufügen“ trägt `data-testid="passkey-hinzufuegen"` und enthält `input[name=name]`;
 * der Warnhinweis trägt `data-testid="passkey-warnung"`. Die Meldung nach dem Löschen steht in der Sitzung unter `status`.
 */
final class PasskeyVerwaltungTest extends TestCase
{
    use PasskeyTestHilfe;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->passkeyAufbauen();
    }

    protected function tearDown(): void
    {
        $this->passkeyAbbauen();

        parent::tearDown();
    }

    private function seite(): DOMXPath
    {
        $html = (string) $this->get(route('userauth.passkey.index'))->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    /** @return list<string> Text je Passkey-Zeile */
    private function zeilen(DOMXPath $seite): array
    {
        $texte = [];
        foreach ($seite->query('//*[@data-testid="passkey-zeile"]') as $zeile) {
            $texte[] = (string) preg_replace('~\s+~u', ' ', $zeile->textContent);
        }

        return $texte;
    }

    private function passkeyId(string $name): int
    {
        return (int) DB::table('user_passkeys')->where('name', $name)->value('id');
    }

    // AK8
    public function test_ak8_gast_wird_auf_die_login_seite_geleitet(): void
    {
        $this->get(route('userauth.passkey.index'))->assertRedirect(route('login'));

        // Gegenprobe: angemeldet ist die Seite erreichbar
        $this->actingAs($this->nutzer());
        $this->get(route('userauth.passkey.index'))->assertOk();
    }

    // AK8
    public function test_ak8_seite_zeigt_nur_eigene_passkeys_mit_datum_und_den_bereich_zum_hinzufuegen(): void
    {
        Carbon::setTestNow('2031-03-14 10:00:00');
        [$ich, $handy] = $this->nutzerMitPasskey(['email' => 'ich@firma.de'], 'Mein Handy');
        $this->passkeyHinzufuegen($ich, 'Mein Laptop');
        $fremder = $this->nutzer(['email' => 'fremd@firma.de']);
        $this->passkeyHinzufuegen($fremder, 'Fremder Schluessel');
        Carbon::setTestNow('2032-05-06 12:00:00');
        $this->passkeyAnmelden($handy)->assertOk(); // setzt „zuletzt benutzt“ nur für das Handy
        $this->neueSitzung();

        $this->actingAs($ich);
        $seite = $this->seite();
        $zeilen = $this->zeilen($seite);

        $this->assertCount(2, $zeilen);
        $handyZeile = current(array_filter($zeilen, fn (string $z): bool => str_contains($z, 'Mein Handy')));
        $laptopZeile = current(array_filter($zeilen, fn (string $z): bool => str_contains($z, 'Mein Laptop')));
        $this->assertNotFalse($handyZeile);
        $this->assertNotFalse($laptopZeile);
        $this->assertStringNotContainsString('Fremder Schluessel', implode(' ', $zeilen));
        $this->assertStringContainsString('2031', $handyZeile, 'Angelegt am fehlt.');
        $this->assertStringContainsString('2032', $handyZeile, 'Zuletzt benutzt fehlt.');
        $this->assertStringContainsString('2031', $laptopZeile);
        $this->assertStringNotContainsString('2032', $laptopZeile, 'Ein nie benutzter Passkey zeigt ein Nutzungsdatum.');
        $this->assertSame(1, $seite->query('//*[@data-testid="passkey-hinzufuegen"]//input[@name="name"]')->length);
    }

    // AK11
    public function test_ak11_loeschen_entfernt_nur_diesen_passkey_leitet_mit_meldung_auf_die_liste_und_das_geraet_meldet_nicht_mehr_an(): void
    {
        [$nutzer, $handy] = $this->nutzerMitPasskey(['email' => 'ich@firma.de'], 'Mein Handy');
        $laptop = $this->passkeyHinzufuegen($nutzer, 'Mein Laptop');
        $this->actingAs($nutzer);

        $this->delete(route('userauth.passkey.destroy', $this->passkeyId('Mein Handy')))
            ->assertRedirect(route('userauth.passkey.index'))
            ->assertSessionHas('status');
        $meldung = (string) session('status');
        $this->assertNotSame('', $meldung);
        $this->get(route('userauth.passkey.index'))->assertOk()->assertSee(e($meldung), false);

        $this->assertSame(['Mein Laptop'], DB::table('user_passkeys')->pluck('name')->all());
        $this->neueSitzung();
        $this->assertPasskeyAbgelehnt($this->passkeyAnmelden($handy));
        $this->passkeyAnmelden($laptop)->assertOk(); // Gegenprobe: der andere Passkey funktioniert
        $this->assertAuthenticatedAs($nutzer);
    }

    // AK11
    public function test_ak11_passkey_eines_anderen_benutzers_liefert_404_und_loescht_nichts(): void
    {
        [$ich] = $this->nutzerMitPasskey(['email' => 'ich@firma.de'], 'Mein Handy');
        [$fremder] = $this->nutzerMitPasskey(['email' => 'fremd@firma.de'], 'Fremdes Handy');
        $this->actingAs($ich);

        $this->delete(route('userauth.passkey.destroy', $this->passkeyId('Fremdes Handy')))->assertNotFound();
        $this->assertSame(2, $this->anzahlPasskeys());

        // Gegenprobe: der eigene Passkey lässt sich über dieselbe Route löschen
        $this->delete(route('userauth.passkey.destroy', $this->passkeyId('Mein Handy')))->assertRedirect(route('userauth.passkey.index'));
        $this->assertSame(['Fremdes Handy'], DB::table('user_passkeys')->pluck('name')->all());
    }

    /** @return array<string, array{0: string, 1: bool}> Verknüpfung und ob die Warnung erscheint */
    public static function verknuepfungen(): array
    {
        return ['keine' => ['', true], 'google' => ['google', false], 'oidc' => ['oidc', false]];
    }

    // AK11
    #[DataProvider('verknuepfungen')]
    public function test_ak11_warnhinweis_bei_nie_ohne_oidc_und_social_der_letzte_passkey_bleibt_loeschbar(string $anbieter, bool $warnung): void
    {
        [$nutzer] = $this->nutzerMitPasskey(['email' => 'ich@firma.de'], 'Mein Handy');
        if ($anbieter !== '') {
            DB::table('user_social_accounts')->insert([
                'user_id' => $nutzer->getKey(), 'provider' => $anbieter, 'provider_user_id' => 'x-1', 'email' => 'ich@firma.de',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->actingAs($nutzer);

        config()->set('userauth.passwort_login', 'immer');
        $this->assertSame(0, $this->seite()->query('//*[@data-testid="passkey-warnung"]')->length, 'Aufbau: bei „immer“ keine Warnung.');

        config()->set('userauth.passwort_login', 'nie');
        $this->assertSame($warnung ? 1 : 0, $this->seite()->query('//*[@data-testid="passkey-warnung"]')->length);

        $this->delete(route('userauth.passkey.destroy', $this->passkeyId('Mein Handy')))->assertRedirect(route('userauth.passkey.index'));
        $this->assertSame(0, $this->anzahlPasskeys($nutzer->getKey()), 'Der letzte Passkey lässt sich nicht löschen.');
    }
}
