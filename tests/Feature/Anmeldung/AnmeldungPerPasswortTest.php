<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Anmeldung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\AnmeldungPerHilfen;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Passwort vergessen und Reset je Modus (userauth-anmeldung-per, #1311): AK9, AK10, AK11.
 * Links stammen aus der verschickten Mail, das Formular wird wie der Browser aus der Seite gelesen.
 */
final class AnmeldungPerPasswortTest extends TestCase
{
    use AnmeldungPerHilfen;
    use RefreshDatabase;

    private const GEMEINSAM = 'gem@example.test';

    private const NEU = 'Neues-Passwort-2026!';

    private User $a;

    private User $b;

    private User $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grundeinstellung('benutzername');
        $this->benutzernameSpalteAnlegen();
        $this->emailIndexEntfernen();
        config()->set('auth.passwords.users.expire', 60);

        $this->a = $this->konto('a.eins', self::GEMEINSAM, passwort: 'Passwort-A-2026!');
        $this->b = $this->konto('b.zwei', self::GEMEINSAM, passwort: 'Passwort-B-2026!');
        $this->c = $this->konto('c.drei', 'c@example.test', passwort: 'Passwort-C-2026!');
    }

    private function anfordern(string $feld, string $kennung): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $this->neueIp()])
            ->from(route('password.forgotten'))
            ->post(route('password.sendtocken'), [$feld => $kennung]);
    }

    private function letzterLink(string $adresse): string
    {
        $nachrichten = $this->nachrichtenAn($adresse);
        $this->assertNotEmpty($nachrichten, "Keine Reset-Mail an {$adresse}.");
        $link = $this->linkAusNachricht(end($nachrichten));
        $this->assertNotNull($link, 'Die Mail enthält keinen Reset-Link.');

        return $link;
    }

    private function kennungAusLink(string $link): string
    {
        $this->assertSame(1, preg_match('~password/editwithtoken/[^/]+/([^/?]+)~', $link, $t), "Link ohne Kennung: {$link}");

        return urldecode($t[1]);
    }

    private function mitKennung(string $link, string $kennung): string
    {
        return (string) preg_replace('~(password/editwithtoken/[^/]+/)[^/?]+~', '${1}'.rawurlencode($kennung), $link);
    }

    /**
     * Öffnet den Link und sendet das Formular wie der Browser mit neuem Passwort.
     *
     * @param  array<string, string>  $ersetze  Feldwerte, die vor dem Senden überschrieben werden
     */
    private function einloesen(string $link, array $ersetze = []): TestResponse
    {
        $html = (string) $this->get($link)->assertOk()->getContent();
        $felder = $this->formularFelder($html, 'updatewithtoken');
        $this->assertArrayHasKey('pwtoken', $felder);

        return $this->withServerVariables(['REMOTE_ADDR' => $this->neueIp()])->post(route('password.updatewithtoken'), array_merge(
            $felder,
            ['password' => self::NEU, 'password_confirmation' => self::NEU],
            $ersetze,
        ));
    }

    private function passwortIst(User $konto, string $passwort): bool
    {
        return Hash::check($passwort, $this->hashVon($konto->id));
    }

    // ------------------------------------------------------------------- AK9

    /**
     * @return array<string, array{string}>
     */
    public static function modi(): array
    {
        return ['email' => ['email'], 'benutzername' => ['benutzername'], 'beides' => ['beides']];
    }

    // AK9
    #[DataProvider('modi')]
    public function test_ak9_seite_passwort_vergessen_hat_das_feld_der_kennung_mit_dem_label_der_anmeldeseite(string $modus): void
    {
        $this->modus($modus);
        $feld = self::kennungsFeld($modus);

        $html = (string) $this->get(route('password.forgotten'))->assertOk()->getContent();

        foreach (['email', 'benutzername', 'login'] as $name) {
            $this->assertSame($name === $feld, $this->eingabe($html, $name) !== null, "Feld {$name} im Modus {$modus}");
        }
        if ($modus === 'email') {
            $this->assertSame('email', $this->eingabe($html, 'email')->getAttribute('type'));

            return;
        }
        $this->assertNotSame('email', $this->eingabe($html, $feld)->getAttribute('type'));
        $this->assertNotSame('', $this->labelText($html, $feld), 'Das Feld hat kein Label');
        $this->assertSame($this->labelText($this->loginSeite(), $feld), $this->labelText($html, $feld));
    }

    // AK9
    public function test_ak9_im_modus_benutzername_geht_der_link_an_die_email_des_gefundenen_kontos_und_nennt_dessen_kennung(): void
    {
        $antwort = $this->anfordern('benutzername', 'b.zwei');

        $antwort->assertRedirect(route('password.tokensend'));
        $this->assertCount(1, $this->gesendeteNachrichten());
        $this->assertCount(1, $this->nachrichtenAn(self::GEMEINSAM));
        $this->assertSame('b.zwei', $this->kennungAusLink($this->letzterLink(self::GEMEINSAM)));
    }

    // AK9 / AK2
    public function test_ak9_im_modus_email_bleibt_der_link_mit_der_email_im_pfad(): void
    {
        $this->modus('email');

        $this->anfordern('email', 'c@example.test')->assertRedirect(route('password.tokensend'));

        $this->assertSame('c@example.test', strtolower($this->kennungAusLink($this->letzterLink('c@example.test'))));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function anforderungen(): array
    {
        // Modus, Kennung, Empfänger der Mail (leer = keine Mail)
        return [
            'benutzername: Benutzername' => ['benutzername', 'c.drei', 'c@example.test'],
            'benutzername: E-Mail wird nicht gesucht' => ['benutzername', 'c@example.test', ''],
            'beides: Benutzername' => ['beides', 'c.drei', 'c@example.test'],
            'beides: eindeutige E-Mail' => ['beides', 'C@Example.test', 'c@example.test'],
            'beides: mehrdeutige E-Mail' => ['beides', self::GEMEINSAM, ''],
            'beides: unbekannte Kennung' => ['beides', 'unbekannt.x', ''],
            'beides: unbekannte E-Mail' => ['beides', 'niemand@example.test', ''],
            'benutzername: unbekannte Kennung' => ['benutzername', 'unbekannt.x', ''],
        ];
    }

    // AK9
    public function test_ak9_antwort_ist_in_allen_faellen_dieselbe_und_mails_gehen_nur_an_gefundene_konten(): void
    {
        $antworten = [];
        $mails = [];
        foreach (self::anforderungen() as $name => [$modus, $kennung, $empfaenger]) {
            $this->travel(2)->minutes(); // Sperre „ein Link je Kennung und Minute“
            $this->modus($modus);
            $vorher = count($this->gesendeteNachrichten());
            $antwort = $this->anfordern(self::kennungsFeld($modus), $kennung);

            $antworten[$name] = [$antwort->getStatusCode(), $antwort->headers->get('Location'), session('message'), session('errors')?->all()];
            $mails[$name] = count($this->gesendeteNachrichten()) - $vorher;
            $this->assertSame($empfaenger === '' ? 0 : 1, $mails[$name], "Mails bei «{$name}»");
            if ($empfaenger !== '') {
                $this->assertNotEmpty($this->nachrichtenAn($empfaenger), "Mail bei «{$name}» an die E-Mail des Kontos");
            }
        }

        $this->assertNotEmpty(array_values($antworten)[0][2], 'Die Seite zeigt eine Meldung');
        $this->assertCount(1, array_unique(array_map('json_encode', $antworten)), 'Die Antworten unterscheiden sich: '.json_encode($antworten));
    }

    // AK9 / Randfall 4
    public function test_ak9_geloeschtes_konto_bekommt_keinen_reset_link(): void
    {
        $this->modus('beides');
        $this->konto('d.vier', 'd@example.test');

        $this->anfordern('login', 'd.vier');
        $this->assertCount(1, $this->nachrichtenAn('d@example.test'), 'Gegenprobe: vor dem Löschen geht die Mail raus');

        User::where('benutzername', 'd.vier')->firstOrFail()->delete();
        $this->travel(2)->minutes();
        $this->anfordern('login', 'd.vier');
        $this->anfordern('login', 'd@example.test');

        $this->assertCount(1, $this->nachrichtenAn('d@example.test'));
    }

    // ------------------------------------------------------------------ AK10

    // AK10
    public function test_ak10_reset_fuer_a_aendert_nur_das_passwort_von_a(): void
    {
        $hashB = $this->hashVon($this->b->id);
        $this->anfordern('benutzername', 'a.eins');

        $this->einloesen($this->letzterLink(self::GEMEINSAM))->assertSessionHasNoErrors();

        $this->assertTrue($this->passwortIst($this->a, self::NEU));
        $this->assertFalse($this->passwortIst($this->a, 'Passwort-A-2026!'));
        $this->assertSame($hashB, $this->hashVon($this->b->id));
        $this->assertTrue($this->passwortIst($this->b, 'Passwort-B-2026!'));
    }

    // AK10
    public function test_ak10_ein_fuer_a_ausgestellter_link_ist_fuer_b_unbrauchbar(): void
    {
        $this->anfordern('benutzername', 'a.eins');
        $link = $this->letzterLink(self::GEMEINSAM);
        $this->assertSame('a.eins', $this->kennungAusLink($link), 'Gegenprobe: der Link gehört zu A');
        $hashA = $this->hashVon($this->a->id);
        $hashB = $this->hashVon($this->b->id);

        $antwort = $this->einloesen($this->mitKennung($link, 'b.zwei'));

        $antwort->assertRedirect()->assertSessionHasErrors();
        $this->assertSame($hashB, $this->hashVon($this->b->id));
        $this->assertSame($hashA, $this->hashVon($this->a->id));
    }

    // AK10
    public function test_ak10_fordern_a_und_b_nacheinander_links_an_bleiben_beide_gleichzeitig_gueltig(): void
    {
        $this->anfordern('benutzername', 'a.eins');
        $this->anfordern('benutzername', 'b.zwei');
        $this->assertCount(2, $this->nachrichtenAn(self::GEMEINSAM));
        $links = [];
        foreach ($this->nachrichtenAn(self::GEMEINSAM) as $nachricht) {
            $link = (string) $this->linkAusNachricht($nachricht);
            $links[$this->kennungAusLink($link)] = $link;
        }
        $this->assertEqualsCanonicalizing(['a.eins', 'b.zwei'], array_keys($links));

        $this->einloesen($links['a.eins'])->assertSessionHasNoErrors();
        $this->neueSitzung();
        $this->einloesen($links['b.zwei'])->assertSessionHasNoErrors();

        $this->assertTrue($this->passwortIst($this->a, self::NEU));
        $this->assertTrue($this->passwortIst($this->b, self::NEU));
    }

    // AK10
    public function test_ak10_eingeloester_und_abgelaufener_link_werden_abgelehnt(): void
    {
        $this->anfordern('benutzername', 'a.eins');
        $this->anfordern('benutzername', 'b.zwei');
        $nachrichten = array_values($this->nachrichtenAn(self::GEMEINSAM));
        $this->assertCount(2, $nachrichten);
        $linkA = (string) $this->linkAusNachricht($nachrichten[0]);
        $linkB = (string) $this->linkAusNachricht($nachrichten[1]);
        $this->assertNotSame($this->kennungAusLink($linkA), $this->kennungAusLink($linkB));

        // eingelöst: zweiter Versuch mit anderem Passwort scheitert, das erste Passwort gilt weiter
        $this->einloesen($linkB)->assertSessionHasNoErrors();
        $hashB = $this->hashVon($this->b->id);
        $this->einloesen($linkB, ['password' => 'Anderes-Passwort-77!', 'password_confirmation' => 'Anderes-Passwort-77!'])->assertSessionHasErrors();
        $this->assertSame($hashB, $this->hashVon($this->b->id));

        // abgelaufen
        $hashA = $this->hashVon($this->a->id);
        $this->travel(61)->minutes();
        $this->einloesen($linkA)->assertSessionHasErrors();
        $this->assertSame($hashA, $this->hashVon($this->a->id));
    }

    // ------------------------------------------------------------------ AK11

    // AK11
    public function test_ak11_anforderung_akzeptiert_die_kennung_ohne_email_format_ausser_im_modus_email(): void
    {
        foreach ([['benutzername', 'c.drei'], ['benutzername', 'kein-email'], ['beides', 'c.drei']] as [$modus, $kennung]) {
            $this->travel(2)->minutes();
            $this->modus($modus);
            $this->anfordern(self::kennungsFeld($modus), $kennung)->assertSessionHasNoErrors()->assertRedirect(route('password.tokensend'));
        }
        $this->assertCount(2, $this->nachrichtenAn('c@example.test'), 'Gegenprobe: c.drei wurde zweimal gefunden');

        // Modus email: Prüfung wie bisher
        $this->travel(2)->minutes();
        $this->modus('email');
        $vorher = count($this->gesendeteNachrichten());
        $this->anfordern('email', 'c.drei')->assertSessionHasErrors('email');
        $this->assertCount($vorher, $this->gesendeteNachrichten());
        $this->anfordern('email', 'c@example.test')->assertSessionHasNoErrors();
        $this->assertCount(3, $this->nachrichtenAn('c@example.test'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function resetPfade(): array
    {
        return [
            'benutzername' => ['benutzername', 'c.drei'],
            'beides per Benutzername' => ['beides', 'c.drei'],
            'beides per eindeutiger E-Mail' => ['beides', 'c@example.test'],
        ];
    }

    // AK11
    #[DataProvider('resetPfade')]
    public function test_ak11_reset_formular_akzeptiert_die_kennung_des_kontos(string $modus, string $kennung): void
    {
        $this->modus($modus);
        $this->anfordern(self::kennungsFeld($modus), $kennung);
        $link = $this->letzterLink('c@example.test');

        $html = (string) $this->get($link)->assertOk()->getContent();
        $this->assertContains($this->kennungAusLink($link), $this->formularFelder($html, 'updatewithtoken'), 'Das Formular trägt die Kennung aus dem Link');
        $this->einloesen($link)->assertSessionHasNoErrors();

        $this->assertTrue($this->passwortIst($this->c, self::NEU));
    }

    // AK11 / AK2
    public function test_ak11_im_modus_email_bleibt_die_email_pruefung_im_reset_formular(): void
    {
        $this->modus('email');
        $this->anfordern('email', 'c@example.test');
        $link = $this->letzterLink('c@example.test');
        $html = (string) $this->get($link)->assertOk()->getContent();
        $felder = $this->formularFelder($html, 'updatewithtoken');
        $emailFeld = array_search('c@example.test', array_map('strtolower', $felder), true);
        $this->assertSame('email', $emailFeld, 'Das Formular trägt die E-Mail im Feld email');
        $hash = $this->hashVon($this->c->id);

        $this->einloesen($link, ['email' => 'c.drei'])->assertSessionHasErrors('email');
        $this->assertSame($hash, $this->hashVon($this->c->id));

        $this->einloesen($link)->assertSessionHasNoErrors(); // Gegenprobe: Link und Aufbau sind in Ordnung
        $this->assertTrue($this->passwortIst($this->c, self::NEU));
    }
}
