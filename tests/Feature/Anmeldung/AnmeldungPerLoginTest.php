<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Anmeldung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\AnmeldungPerHilfen;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Anmeldung je Modus von `userauth.anmeldung_per` (userauth-anmeldung-per, #1311): AK1–AK8 und die Randfälle zur Anmeldung.
 * Modus `email` dient als Gegenprobe zu den anderen Modi (AK2). Alle Konten haben das Passwort `password`, außer wo genannt.
 */
final class AnmeldungPerLoginTest extends TestCase
{
    use AnmeldungPerHilfen;
    use RefreshDatabase;

    private const GEMEINSAM = 'gem@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->grundeinstellung();
        $this->benutzernameSpalteAnlegen();
        $this->emailIndexEntfernen();
    }

    /** Konten A und B teilen eine E-Mail, C hat eine eigene. */
    private function standardKonten(): void
    {
        $this->konto('a.eins', self::GEMEINSAM);
        $this->konto('b.zwei', self::GEMEINSAM);
        $this->konto('c.drei', 'c@example.test');
    }

    private function fehler(string $feld): string
    {
        return (string) session('errors')?->first($feld);
    }

    private function hatRememberCookie(TestResponse $antwort): bool
    {
        foreach ($antwort->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function modi(): array
    {
        return [
            'email' => ['email', 'email', 'c@example.test'],
            'benutzername' => ['benutzername', 'benutzername', 'c.drei'],
            'beides' => ['beides', 'login', 'c.drei'],
        ];
    }

    // ------------------------------------------------------------------- AK1

    // AK1
    public function test_ak1_standard_der_paket_config_ist_email_und_kommt_aus_der_umgebungsvariable(): void
    {
        $datei = dirname(__DIR__, 3).'/src/Config/config.php';
        $this->assertSame('email', (require $datei)['anmeldung_per'], 'Standard ohne Variable');
        try {
            putenv('USERAUTH_ANMELDUNG_PER=beides');
            $this->assertSame('beides', (require $datei)['anmeldung_per']);
        } finally {
            putenv('USERAUTH_ANMELDUNG_PER');
        }
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function wertetabelle(): array
    {
        return [
            'email' => ['email', 'email'],
            'EMAIL gross, mit Leerzeichen' => ['  EMAIL ', 'email'],
            'benutzername' => ['benutzername', 'benutzername'],
            'Benutzername gemischt' => ['Benutzername', 'benutzername'],
            'benutzername mit Leerzeichen' => ['  benutzername  ', 'benutzername'],
            'beides' => ['beides', 'login'],
            'BEIDES mit Leerzeichen' => [' BEIDES ', 'login'],
            'unbekannter Wert' => ['mail', 'email'],
            'Liste' => ['benutzername,email', 'email'],
            'leer' => ['', 'email'],
            'nur Leerzeichen' => ['   ', 'email'],
            'null' => [null, 'email'],
            'true' => [true, 'email'],
            'Zahl' => [5, 'email'],
        ];
    }

    // AK1
    #[DataProvider('wertetabelle')]
    public function test_ak1_werte_gelten_ohne_beachtung_von_gross_kleinschreibung_und_leerzeichen_alles_andere_wirkt_wie_email(mixed $wert, string $erwartetesFeld): void
    {
        $this->modus('beides');
        $this->assertNotNull($this->eingabe($this->loginSeite(), 'login'), 'Gegenprobe: die Config wirkt');

        $this->modus($wert);

        $html = $this->loginSeite();
        foreach (['email', 'benutzername', 'login'] as $feld) {
            $this->assertSame($feld === $erwartetesFeld, $this->eingabe($html, $feld) !== null, "Feld {$feld} bei Wert ".json_encode($wert));
        }
    }

    // ------------------------------------------------------------------- AK2

    /**
     * @return array<string, array{?string}>
     */
    public static function emailModi(): array
    {
        return ['email' => ['email'], 'nicht gesetzt (null)' => [null]];
    }

    // AK2
    #[DataProvider('emailModi')]
    public function test_ak2_modus_email_zeigt_feld_email_mit_type_email_und_meldet_per_email_an(?string $modus): void
    {
        $this->konto('anna.m', 'anna@example.test');
        $this->modus($modus);

        $html = $this->loginSeite();
        $email = $this->eingabe($html, 'email');
        $this->assertNotNull($email);
        $this->assertSame('email', $email->getAttribute('type'));
        $this->assertSame('email', $email->getAttribute('autocomplete'));
        $this->assertNull($this->eingabe($html, 'benutzername'));
        $this->assertNull($this->eingabe($html, 'login'));

        // Gegenprobe: das Feld benutzername wirkt in diesem Modus nicht, die E-Mail schon
        $this->anmelden('benutzername', 'anna.m')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->anmelden('email', 'anna@example.test')->assertRedirect('/');
        $this->assertAuthenticated();
    }

    // ------------------------------------------------------------------- AK3

    // AK3
    public function test_ak3_seite_zeigt_textfeld_benutzername_ohne_feld_email(): void
    {
        $this->modus('benutzername');

        $html = $this->loginSeite();

        $feld = $this->eingabe($html, 'benutzername');
        $this->assertNotNull($feld);
        $this->assertNotSame('email', $feld->getAttribute('type'));
        $this->assertContains($feld->getAttribute('type'), ['text', '']);
        $this->assertSame('username', $feld->getAttribute('autocomplete'));
        $this->assertNull($this->eingabe($html, 'email'));
        $this->assertNull($this->eingabe($html, 'login'));
    }

    // AK3
    public function test_ak3_anmeldung_per_benutzername_mit_angemeldet_bleiben_und_weiterleitung(): void
    {
        $this->modus('benutzername');
        $konto = $this->konto('anna.m', 'anna@example.test');
        config()->set('userauth.redirect_after_login', '/zielseite');

        $ohne = $this->anmelden('benutzername', 'anna.m');
        $ohne->assertRedirect('/zielseite');
        $this->assertAuthenticatedAs($konto);
        $this->assertFalse($this->hatRememberCookie($ohne), 'Gegenprobe: ohne „angemeldet bleiben“ kein Remember-Cookie');

        $this->neueSitzung();
        $mit = $this->anmelden('benutzername', 'anna.m', extra: ['remember' => 'on']);
        $mit->assertRedirect('/zielseite');
        $this->assertAuthenticatedAs($konto);
        $this->assertTrue($this->hatRememberCookie($mit));
    }

    // AK3
    public function test_ak3_eine_email_meldet_nicht_an_auch_wenn_sie_zu_einem_konto_gehoert(): void
    {
        $this->modus('benutzername');
        $this->konto('anna.m', 'anna@example.test');

        $this->anmelden('benutzername', 'anna@example.test')->assertSessionHasErrors('benutzername');
        $this->assertGuest();

        $this->anmelden('benutzername', 'anna.m')->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    // AK3
    public function test_ak3_benutzername_ohne_beachtung_der_gross_kleinschreibung_und_um_randleerzeichen_bereinigt(): void
    {
        $this->modus('benutzername');
        $konto = $this->konto('anna.m', 'anna@example.test');

        $this->anmelden('benutzername', 'ANNA.M')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($konto);
        $this->neueSitzung();
        $this->assertGuest();

        $this->anmelden('benutzername', "  Anna.M \t")->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($konto);
        $this->neueSitzung();

        $this->anmelden('benutzername', 'anna.mm')->assertSessionHasErrors('benutzername');
        $this->assertGuest();
    }

    // Randfall 2 (alle Modi)
    #[DataProvider('modi')]
    public function test_randfall_kennung_nur_aus_leerzeichen_ergibt_feld_erforderlich(string $modus, string $feld, string $gueltig): void
    {
        $this->modus($modus);
        $this->standardKonten();

        $this->anmelden($feld, '   ')->assertSessionHasErrors($feld);
        $this->assertGuest();

        $this->anmelden($feld, $gueltig)->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    // ------------------------------------------------------------------- AK4

    // AK4
    public function test_ak4_jedes_konto_mit_gemeinsamer_email_meldet_sich_mit_eigenem_benutzernamen_und_passwort_an(): void
    {
        $this->modus('benutzername');
        $a = $this->konto('a.eins', self::GEMEINSAM, passwort: 'Passwort-A-2026!');
        $b = $this->konto('b.zwei', self::GEMEINSAM, passwort: 'Passwort-B-2026!');
        $bVorher = DB::table('users')->where('id', $b->id)->first();

        // Kreuzprobe: fremdes Passwort geht nicht
        $this->anmelden('benutzername', 'a.eins', 'Passwort-B-2026!')->assertSessionHasErrors('benutzername');
        $this->assertGuest();

        $this->anmelden('benutzername', 'a.eins', 'Passwort-A-2026!', ['remember' => 'on'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($a);
        $this->assertEquals($bVorher, DB::table('users')->where('id', $b->id)->first(), 'Die Anmeldung von A hat B berührt.');

        $this->neueSitzung();
        $this->anmelden('benutzername', 'b.zwei', 'Passwort-B-2026!')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($b);
    }

    // ------------------------------------------------------------------- AK5

    // AK5
    public function test_ak5_seite_zeigt_textfeld_login_mit_label_benutzername_oder_email(): void
    {
        $this->modus('beides');

        $html = $this->loginSeite();

        $feld = $this->eingabe($html, 'login');
        $this->assertNotNull($feld);
        $this->assertNotSame('email', $feld->getAttribute('type'));
        $this->assertSame('username', $feld->getAttribute('autocomplete'));
        $this->assertStringContainsString('Benutzername oder E-Mail', $this->labelText($html, 'login'));
        $this->assertNull($this->eingabe($html, 'email'));
        $this->assertNull($this->eingabe($html, 'benutzername'));
    }

    // AK5
    public function test_ak5_benutzername_und_eindeutige_email_melden_an_gemeinsame_email_nicht(): void
    {
        $this->modus('beides');
        $this->standardKonten();
        $ohneName = $this->konto('wird-null', 'null@example.test');
        DB::table('users')->where('id', $ohneName->id)->update(['benutzername' => null]);
        $erfolge = [
            ['a.eins', 'a.eins'], ['C.DREI', 'c.drei'], ['c@example.test', 'c.drei'], ['  C@Example.Test ', 'c.drei'],
            ['null@example.test', 'wird-null'], // Randfall 1: Konto ohne Benutzername nur per eindeutiger E-Mail
        ];
        $ids = ['a.eins' => User::where('benutzername', 'a.eins')->value('id'), 'c.drei' => User::where('benutzername', 'c.drei')->value('id'), 'wird-null' => $ohneName->id];

        foreach ($erfolge as [$kennung, $konto]) {
            $this->neueSitzung();
            $this->anmelden('login', $kennung)->assertSessionHasNoErrors();
            $this->assertSame($ids[$konto], auth()->id(), "Kennung {$kennung}");
        }

        $this->neueSitzung();
        $this->anmelden('login', self::GEMEINSAM)->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    // AK5
    public function test_ak5_bei_mehrdeutiger_email_wird_kein_passwort_gegen_ein_konto_geprueft(): void
    {
        $this->modus('beides');
        // Ein Passwort-Hash im falschen Verfahren löst beim Prüfen eine Exception aus: sie belegt, dass geprüft wurde.
        $this->konto('a.eins', self::GEMEINSAM, ['password' => 'kein-bcrypt-hash']);
        $this->konto('b.zwei', self::GEMEINSAM, ['password' => 'kein-bcrypt-hash']);
        $this->konto('e.vier', 'e@example.test', ['password' => 'kein-bcrypt-hash']);

        $this->anmelden('login', 'e.vier')->assertStatus(500); // Gegenprobe: bei eindeutigem Konto wird das Passwort geprüft

        $this->neueSitzung();
        $antwort = $this->anmelden('login', self::GEMEINSAM);
        $antwort->assertRedirect()->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    // AK5
    public function test_ak5_ein_benutzername_hat_vorrang_vor_einer_gleichlautenden_email(): void
    {
        $this->modus('beides');
        $x = $this->konto('dual@example.test', 'x@example.test', passwort: 'Passwort-X-2026!');
        $this->konto('y.fuenf', 'dual@example.test', passwort: 'Passwort-Y-2026!');

        $this->anmelden('login', 'dual@example.test', 'Passwort-Y-2026!')->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->anmelden('login', 'dual@example.test', 'Passwort-X-2026!')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($x);
    }

    // Randfall 5
    public function test_randfall_emails_die_sich_nur_in_der_schreibweise_unterscheiden_zaehlen_als_gleich(): void
    {
        $this->modus('beides');
        $this->konto('p.eins', 'Max@Example.test');
        $this->konto('q.zwei', 'max@example.TEST');

        foreach (['max@example.test', 'Max@Example.test'] as $kennung) {
            $this->anmelden('login', $kennung)->assertSessionHasErrors('login');
            $this->assertGuest();
        }

        $this->anmelden('login', 'p.eins')->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    // Randfall 1
    public function test_randfall_konto_ohne_benutzername_kann_sich_im_modus_benutzername_nicht_anmelden(): void
    {
        $this->modus('benutzername');
        $konto = $this->konto('wird-null', 'null@example.test');
        DB::table('users')->where('id', $konto->id)->update(['benutzername' => null]);
        $this->konto('anna.m', 'anna@example.test');

        foreach (['', 'null@example.test', 'null', 'NULL'] as $kennung) {
            $this->anmelden('benutzername', $kennung)->assertSessionHasErrors('benutzername');
            $this->assertGuest();
        }

        $this->anmelden('benutzername', 'anna.m')->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function geloeschte(): array
    {
        return [
            'Modus benutzername' => ['benutzername', 'g.k'],
            'beides per Benutzername' => ['beides', 'g.k'],
            'beides per E-Mail' => ['beides', 'g@example.test'],
        ];
    }

    // Randfall 4
    #[DataProvider('geloeschte')]
    public function test_randfall_geloeschtes_konto_meldet_sich_weder_per_benutzername_noch_per_email_an(string $modus, string $kennung): void
    {
        $this->modus($modus);
        $feld = self::kennungsFeld($modus);
        $konto = $this->konto('g.k', 'g@example.test');

        $this->anmelden($feld, $kennung)->assertSessionHasNoErrors(); // Gegenprobe: vor dem Löschen geht es
        $this->assertAuthenticatedAs($konto);
        $this->neueSitzung();

        $konto->delete();

        $this->anmelden($feld, $kennung)->assertSessionHasErrors($feld);
        $this->assertGuest();
    }

    // ------------------------------------------------------------------- AK6

    /**
     * @return array<string, array{string}>
     */
    public static function modiMitKennung(): array
    {
        return ['benutzername' => ['benutzername'], 'beides' => ['beides']];
    }

    // AK6
    #[DataProvider('modiMitKennung')]
    public function test_ak6_jede_fehlgeschlagene_anmeldung_liefert_denselben_text_und_status(string $modus): void
    {
        $this->modus($modus);
        $feld = self::kennungsFeld($modus);
        $this->standardKonten();
        $faelle = [
            'unbekannt' => ['unbekannt.x', 'password'],
            'falsches Passwort' => ['a.eins', 'falsch'],
        ];
        if ($modus === 'beides') {
            $faelle['mehrdeutige E-Mail'] = [self::GEMEINSAM, 'password'];
            $faelle['unbekannte E-Mail'] = ['niemand@example.test', 'password'];
        }

        $antworten = [];
        foreach ($faelle as $name => [$kennung, $passwort]) {
            $antwort = $this->anmelden($feld, $kennung, $passwort, json: true);
            $antwort->assertStatus(422);
            $antworten[$name] = $antwort->json();
            $this->assertNotEmpty($antworten[$name]['errors'][$feld] ?? null, "{$name}: Fehler am Feld der Kennung");
            $this->assertGuest();
        }

        $this->assertCount(1, array_unique(array_map('json_encode', $antworten)), 'Die Antworten unterscheiden sich: '.json_encode($antworten));
        if ($modus === 'beides') {
            $this->assertStringContainsStringIgnoringCase('benutzername', $antworten['unbekannt']['errors'][$feld][0]);
        }
        $this->anmelden($feld, 'a.eins', json: true)->assertRedirect();
        $this->assertAuthenticated(); // Gegenprobe: mit richtigen Daten keine Fehlermeldung
    }

    // ------------------------------------------------------------------- AK7

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function sperrFaelle(): array
    {
        return [
            'email' => ['email', 'email', 'c@example.test'],
            'benutzername' => ['benutzername', 'benutzername', 'c.drei'],
            'beides per Benutzername' => ['beides', 'login', 'c.drei'],
            'beides per E-Mail' => ['beides', 'login', 'c@example.test'],
        ];
    }

    // AK7
    #[DataProvider('sperrFaelle')]
    public function test_ak7_drosselung_je_kennung_und_ip_kleingeschrieben_und_bereinigt(string $modus, string $feld, string $kennung): void
    {
        $this->modus($modus);
        $this->standardKonten();
        $varianten = [$kennung, strtoupper($kennung), ' '.$kennung, ucfirst($kennung).' ', strtoupper($kennung).'  '];

        foreach ($varianten as $variante) {
            $this->anmelden($feld, $variante, 'falsch', json: true)->assertStatus(422);
        }

        $gesperrt = $this->anmelden($feld, $kennung, json: true);
        $gesperrt->assertStatus(429);
        $this->assertNotEmpty($gesperrt->json("errors.{$feld}"), 'Der Lockout nennt die Kennung (Fehler am Feld der Kennung)');
        $this->assertGuest();

        // Gegenproben: andere Kennung nicht gesperrt, dieselbe Kennung von anderer IP geht
        $this->anmelden($feld, 'unbekannt.x', 'falsch', json: true)->assertStatus(422);
        $this->withServerVariables(['REMOTE_ADDR' => $this->neueIp()]);
        $this->anmelden($feld, $kennung, json: true)->assertRedirect();
        $this->assertAuthenticated();
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function auditFaelle(): array
    {
        return [
            'email' => ['email', 'nobody@example.test', 'c@example.test', 'c.drei'],
            'benutzername' => ['benutzername', 'nobody.x', 'b.zwei', 'b.zwei'],
            'beides per Benutzername' => ['beides', 'nobody.x', 'b.zwei', 'b.zwei'],
            'beides per E-Mail' => ['beides', 'nobody@example.test', 'c@example.test', 'c.drei'],
        ];
    }

    // AK7
    #[DataProvider('auditFaelle')]
    public function test_ak7_audit_log_schreibt_die_eingegebene_kennung_in_email_und_bei_erfolg_das_konto(string $modus, string $falsch, string $richtig, string $erwartet): void
    {
        $this->modus($modus);
        $feld = self::kennungsFeld($modus);
        $this->auditTabelleBereitstellen();
        config()->set('userauth.audit_log_enabled', true);
        $this->standardKonten();
        $konto = User::where('benutzername', $erwartet)->firstOrFail();

        $this->anmelden($feld, $falsch)->assertSessionHasErrors($feld);
        $fehlversuch = DB::table('user_auth_audit_logs')->where('event', 'failed')->get();
        $this->assertCount(1, $fehlversuch);
        $this->assertSame($falsch, $fehlversuch[0]->email);
        $this->assertNull($fehlversuch[0]->user_id);

        $this->anmelden($feld, $richtig)->assertSessionHasNoErrors();
        $erfolg = DB::table('user_auth_audit_logs')->where('event', 'login')->get();
        $this->assertCount(1, $erfolg);
        $this->assertSame($konto->id, (int) $erfolg[0]->user_id, 'Der Eintrag gehört zum angemeldeten Konto');
        $this->assertSame($konto->email, $erfolg[0]->email);
        $this->assertSame(1, DB::table('user_auth_audit_logs')->where('event', 'failed')->count());
    }

    // AK7
    #[DataProvider('modiMitKennung')]
    public function test_ak7_zwei_faktor_laeuft_nach_der_kennungs_anmeldung_und_sendet_an_die_email_des_kontos(string $modus): void
    {
        $this->modus($modus);
        $feld = self::kennungsFeld($modus);
        config()->set('userauth.two_factor_enabled', true);
        $this->standardKonten();
        $b = User::where('benutzername', 'b.zwei')->firstOrFail();

        $this->anmelden($feld, 'unbekannt.x')->assertSessionHasErrors($feld);
        $this->assertSame([], $this->gesendeteNachrichten(), 'Gegenprobe: ohne gültige Anmeldung kein Code');

        $this->anmelden($feld, 'b.zwei')->assertRedirect(route('verify.index'));
        $this->assertGuest();
        $this->assertCount(1, $this->nachrichtenAn(self::GEMEINSAM));
        $this->from(route('verify.index'));
        $this->senden('post', route('verify.store'), ['two_factor_code' => $this->zweiFaktorCode(self::GEMEINSAM)]);
        $this->assertAuthenticatedAs($b);

        $this->neueSitzung();
        $this->anmelden($feld, 'b.zwei')->assertRedirect(route('verify.index'));
        $this->travel(61)->seconds();
        $this->senden('post', route('verify.resend'))->assertRedirect();
        $this->assertCount(3, $this->nachrichtenAn(self::GEMEINSAM), 'Erneut senden geht an die E-Mail des Kontos');
        $this->from(route('verify.index'));
        $this->senden('post', route('verify.store'), ['two_factor_code' => $this->zweiFaktorCode(self::GEMEINSAM)]);
        $this->assertAuthenticatedAs($b);
    }

    // ------------------------------------------------------------------- AK8

    /**
     * @return array<string, array{string, string}>
     */
    public static function bestaetigungOffen(): array
    {
        return [
            'benutzername' => ['benutzername', 'neu.k'],
            'beides per Benutzername' => ['beides', 'neu.k'],
            'beides per E-Mail' => ['beides', 'neu@example.test'],
            'email (unverändert)' => ['email', 'neu@example.test'],
        ];
    }

    // AK8
    #[DataProvider('bestaetigungOffen')]
    public function test_ak8_bestaetigung_offen_erscheint_am_feld_der_kennung_und_die_eingabe_bleibt(string $modus, string $kennung): void
    {
        $this->modus($modus);
        $feld = self::kennungsFeld($modus);
        $this->konto('neu.k', 'neu@example.test', ['self_registered' => true, 'email_verified_at' => null]);
        $this->konto('alt.k', 'alt@example.test');

        $this->anmelden($feld, $kennung)->assertRedirect(route('login'))->assertSessionHasErrors($feld);
        $this->assertSame(__('userauth::registrierung.bestaetigung_offen'), $this->fehler($feld));
        $this->assertNotContains('email', $feld === 'email' ? [] : session('errors')->getBag('default')->keys());
        $this->assertSame($kennung, session()->getOldInput($feld));
        $this->assertGuest();

        $html = (string) $this->senden('get', '/login')->getContent();
        $this->assertSame($kennung, $this->eingabe($html, $feld)?->getAttribute('value'), 'Die Eingabe bleibt im Feld der Kennung erhalten');
        $this->assertStringContainsString(e(__('userauth::registrierung.bestaetigung_offen')), $html);

        // Gegenprobe: ein bestätigtes Konto meldet sich an
        $this->neueSitzung();
        $this->anmelden($feld, str_replace('neu', 'alt', $kennung))->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }
}
