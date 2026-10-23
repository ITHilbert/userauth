<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Haertung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\NachrichtenAbfangen;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Passwort zurücksetzen. Abgedeckt: AK4–AK8 (userauth-haertung, Befunde S2, S3, S12).
 *
 * Die Mail wird über Mail- und Notification-Fake abgefangen; der Reset-Link wird aus dem gerenderten Inhalt gelesen.
 * Felder des Formulars (Bestand): email, pwtoken, password, password_confirmation.
 */
final class PasswortResetTest extends TestCase
{
    use NachrichtenAbfangen;
    use RefreshDatabase;

    private const ALTES_PASSWORT = 'password';

    private const NEUES_PASSWORT = 'Neues-Passwort-2026!';

    private const GESPEICHERTE_ADRESSE = 'Max.Muster@Firma.de';

    private User $nutzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nachrichtenAbfangen();
        config()->set('userauth.redirect_after_login', '/');
        config()->set('userauth.password_policy.enabled', false);

        $this->nutzer = User::factory()->create(['email' => self::GESPEICHERTE_ADRESSE]);
    }

    // ---------------------------------------------------------------- Hilfen

    private function anfordern(string $email): TestResponse
    {
        return $this->post(route('password.sendtocken'), ['email' => $email]);
    }

    /**
     * Fordert an und liefert den Klartext-Token aus dem Link der Mail.
     */
    private function tokenAnfordern(?User $nutzer = null): string
    {
        $nutzer ??= $this->nutzer;

        $this->anfordern($nutzer->email)->assertRedirect();

        $nachrichten = $this->nachrichtenAn($nutzer->email);
        $this->assertNotEmpty($nachrichten, 'Es wurde keine Reset-Mail versendet.');

        return $this->tokenAusLink((string) $this->linkAusNachricht(end($nachrichten)));
    }

    private function tokenAusLink(string $link): string
    {
        $this->assertNotSame('', $link, 'Die Mail enthält keinen Reset-Link (Route password.editwithtoken).');
        $pfad = (string) parse_url($link, PHP_URL_PATH);
        $this->assertSame(1, preg_match('~password/editwithtoken/([^/]+)~', $pfad, $treffer));

        return urldecode($treffer[1]);
    }

    /**
     * @param  array<string, mixed>  $felder
     */
    private function zuruecksetzen(array $felder, bool $json = false): TestResponse
    {
        $daten = array_merge([
            'email' => $this->nutzer->email,
            'password' => self::NEUES_PASSWORT,
            'password_confirmation' => self::NEUES_PASSWORT,
        ], $felder);
        $formular = route('password.editwithtoken', ['token' => 'x', 'email' => $this->nutzer->email]);

        return $json
            ? $this->postJson(route('password.updatewithtoken'), $daten)
            : $this->from($formular)->post(route('password.updatewithtoken'), $daten);
    }

    private function passwortUnveraendert(?User $nutzer = null): bool
    {
        $nutzer ??= $this->nutzer;

        return DB::table('users')->where('id', $nutzer->id)->value('password') === $nutzer->password;
    }

    /**
     * Abgelehnt mit Hinweis, kein Serverfehler. Bei Weiterleitung steht der Hinweis in der Session und in der
     * angezeigten Seite, bei JSON in der Antwort.
     */
    private function assertAbgelehnt(TestResponse $antwort, bool $json): void
    {
        $this->assertContains($antwort->getStatusCode(), [302, 422], 'Erwartet Ablehnung (302/422), erhalten '.$antwort->getStatusCode());

        if ($antwort->getStatusCode() === 422) {
            $this->assertNotEmpty($antwort->json('errors') ?? $antwort->json('message'), 'Ablehnung ohne Hinweis.');

            return;
        }

        $fehler = session('errors');
        $this->assertNotNull($fehler, 'Ablehnung ohne Hinweis in der Session.');
        $this->assertNotEmpty($fehler->all(), 'Ablehnung ohne Hinweis in der Session.');

        if (! $json) {
            $this->get($antwort->headers->get('Location'))->assertOk()->assertSee($fehler->all()[0]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function alleTabellenInhalte(): array
    {
        $inhalte = [];
        foreach (DB::select("select name from sqlite_master where type = 'table'") as $tabelle) {
            $inhalte[$tabelle->name] = DB::table($tabelle->name)->get()->all();
        }

        return $inhalte;
    }

    // ------------------------------------------------------------------- AK4

    // AK4
    public function test_beim_anfordern_geht_genau_eine_mail_mit_reset_link_an_das_konto(): void
    {
        $this->anfordern(self::GESPEICHERTE_ADRESSE)->assertRedirect();

        $nachrichten = $this->gesendeteNachrichten();
        $this->assertCount(1, $nachrichten);
        $this->assertSame([self::GESPEICHERTE_ADRESSE], $nachrichten[0]['an']);
        $this->assertNotNull($this->linkAusNachricht($nachrichten[0]), 'Die Mail enthält keinen Reset-Link.');
    }

    // AK4
    public function test_reset_link_der_mail_oeffnet_das_formular_mit_dem_token(): void
    {
        $token = $this->tokenAnfordern();
        $link = $this->linkAusNachricht($this->nachrichtenAn(self::GESPEICHERTE_ADRESSE)[0]);

        $this->get($link)->assertOk()->assertSee($token);
    }

    // AK4 (Randfall Groß-/Kleinschreibung)
    public function test_mail_geht_an_die_gespeicherte_schreibweise_auch_bei_abweichender_eingabe(): void
    {
        $this->anfordern('MAX.MUSTER@firma.DE')->assertRedirect();

        $this->assertCount(1, $this->gesendeteNachrichten());
        $this->assertCount(1, $this->nachrichtenAn(self::GESPEICHERTE_ADRESSE), 'Mail muss exakt an die gespeicherte Schreibweise gehen.');
    }

    // AK4
    public function test_token_wird_nirgends_im_klartext_gespeichert(): void
    {
        $token = $this->tokenAnfordern();

        foreach ($this->alleTabellenInhalte() as $tabelle => $zeilen) {
            $this->assertStringNotContainsString($token, json_encode($zeilen, JSON_THROW_ON_ERROR), "Klartext-Token steht in Tabelle {$tabelle}.");
        }
    }

    // AK4
    public function test_nach_dem_anfordern_wird_die_meldung_auf_der_zielseite_angezeigt(): void
    {
        $antwort = $this->anfordern(self::GESPEICHERTE_ADRESSE);
        $antwort->assertRedirect(route('password.tokensend'));
        $meldung = session('message');
        $this->assertNotEmpty($meldung);

        $this->get($antwort->headers->get('Location'))->assertOk()->assertSee($meldung);
    }

    // ------------------------------------------------------------------- AK5

    // AK5
    public function test_unbekannte_email_erhaelt_dieselbe_weiterleitung_und_meldung_wie_ein_bekanntes_konto(): void
    {
        $bekannt = $this->anfordern(self::GESPEICHERTE_ADRESSE);
        $bekanntZiel = $bekannt->headers->get('Location');
        $bekanntMeldung = session('message');

        $unbekannt = $this->anfordern('niemand@example.org');

        $this->assertSame($bekanntZiel, $unbekannt->headers->get('Location'));
        $this->assertNotEmpty($bekanntMeldung);
        $this->assertSame($bekanntMeldung, session('message'));
    }

    // AK5
    public function test_unbekannte_email_zeigt_die_meldung_auch_in_der_seite(): void
    {
        $antwort = $this->anfordern('niemand@example.org');
        $meldung = session('message');
        $this->assertNotEmpty($meldung);

        $this->get($antwort->headers->get('Location'))->assertOk()->assertSee($meldung);
    }

    // AK5
    public function test_unbekannte_email_loest_keine_mail_aus(): void
    {
        $this->anfordern('niemand@example.org');

        $this->assertSame([], $this->gesendeteNachrichten());
    }

    // AK5
    public function test_unbekannte_email_fuehrt_nicht_zu_einem_serverfehler(): void
    {
        $this->anfordern('niemand@example.org')->assertRedirect()->assertSessionHasNoErrors();
    }

    // AK5 ("aktives Konto")
    public function test_geloeschtes_konto_erhaelt_keine_mail_und_dieselbe_antwort(): void
    {
        $bekannt = $this->anfordern(self::GESPEICHERTE_ADRESSE);
        $bekanntZiel = $bekannt->headers->get('Location');
        $bekanntMeldung = session('message');
        $geloescht = User::factory()->create(['email' => 'weg@firma.de']);
        $geloescht->delete();

        $antwort = $this->anfordern('weg@firma.de');

        $this->assertSame($bekanntZiel, $antwort->headers->get('Location'));
        $this->assertSame($bekanntMeldung, session('message'));
        $this->assertSame([], $this->nachrichtenAn('weg@firma.de'));
    }

    // ------------------------------------------------------------------- AK6

    // AK6
    public function test_mit_gueltigem_token_wird_das_passwort_geaendert(): void
    {
        $token = $this->tokenAnfordern();

        $this->zuruecksetzen(['pwtoken' => $token])->assertRedirect()->assertSessionHasNoErrors();

        $neu = $this->nutzer->fresh();
        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $neu->password));
        $this->assertFalse(Hash::check(self::ALTES_PASSWORT, $neu->password));
    }

    // AK6
    public function test_beim_zuruecksetzen_wird_password_changed_at_gesetzt(): void
    {
        $this->travelTo(now()->startOfSecond());
        $token = $this->tokenAnfordern();
        $this->assertNull(DB::table('users')->where('id', $this->nutzer->id)->value('password_changed_at'));

        $this->zuruecksetzen(['pwtoken' => $token]);

        $gesetzt = DB::table('users')->where('id', $this->nutzer->id)->value('password_changed_at');
        $this->assertNotNull($gesetzt);
        $this->assertEqualsWithDelta(now()->getTimestamp(), strtotime((string) $gesetzt), 5);
    }

    // AK6
    public function test_beim_zuruecksetzen_wird_die_passwort_historie_fortgeschrieben(): void
    {
        config()->set('userauth.password_policy.enabled', true);
        $altesHash = $this->nutzer->password;
        $token = $this->tokenAnfordern();

        $this->zuruecksetzen(['pwtoken' => $token])->assertSessionHasNoErrors();

        $this->assertTrue(Schema::hasTable('user_password_histories'));
        $this->assertDatabaseHas('user_password_histories', ['user_id' => $this->nutzer->id, 'password' => $altesHash]);
    }

    // AK6
    public function test_token_ist_nach_einmaliger_nutzung_verbraucht(): void
    {
        $token = $this->tokenAnfordern();
        $this->zuruecksetzen(['pwtoken' => $token])->assertSessionHasNoErrors();
        $nachErstemMal = $this->nutzer->fresh();

        $zweites = $this->zuruecksetzen([
            'pwtoken' => $token,
            'password' => 'Anderes-Passwort-77!',
            'password_confirmation' => 'Anderes-Passwort-77!',
        ]);

        $this->assertAbgelehnt($zweites, false);
        $this->assertTrue($this->passwortUnveraendert($nachErstemMal));
        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $this->nutzer->fresh()->password));
    }

    // AK6 + AK4 (Randfall Groß-/Kleinschreibung der E-Mail)
    public function test_zuruecksetzen_ist_unabhaengig_von_der_schreibweise_der_email(): void
    {
        $token = $this->tokenAnfordern();

        $this->zuruecksetzen(['pwtoken' => $token, 'email' => 'max.muster@FIRMA.de'])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $this->nutzer->fresh()->password));
    }

    // AK6 (Randfall: neues Passwort muss gültig sein)
    public function test_zu_kurzes_neues_passwort_wird_abgelehnt_und_token_bleibt_nutzbar(): void
    {
        $token = $this->tokenAnfordern();

        $zuKurz = $this->zuruecksetzen(['pwtoken' => $token, 'password' => 'kurz', 'password_confirmation' => 'kurz']);
        $this->assertAbgelehnt($zuKurz, false);
        $this->assertTrue($this->passwortUnveraendert());

        $this->zuruecksetzen(['pwtoken' => $token])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $this->nutzer->fresh()->password));
    }

    // ------------------------------------------------------------------- AK7

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function ungueltigeTokens(): array
    {
        return [
            'falscher Text' => ['falsches-token', false],
            'String 0' => ['0', false],
            'leer' => ['', false],
            'Zahl 0 per JSON' => [0, true],
            'Array per JSON' => [['a', 'b'], true],
            'verschachteltes Array per JSON' => [['x' => ['y']], true],
            'true per JSON' => [true, true],
        ];
    }

    // AK7
    #[DataProvider('ungueltigeTokens')]
    public function test_ungueltiges_token_wird_mit_hinweis_abgelehnt(mixed $token, bool $json): void
    {
        $this->tokenAnfordern();

        $this->assertAbgelehnt($this->zuruecksetzen(['pwtoken' => $token], $json), $json);
    }

    // AK7
    #[DataProvider('ungueltigeTokens')]
    public function test_ungueltiges_token_laesst_das_passwort_unveraendert(mixed $token, bool $json): void
    {
        $this->tokenAnfordern();

        $this->zuruecksetzen(['pwtoken' => $token], $json);

        $this->assertTrue($this->passwortUnveraendert());
    }

    // AK7
    #[DataProvider('ungueltigeTokens')]
    public function test_ungueltiges_token_fuehrt_nicht_zu_einem_serverfehler(mixed $token, bool $json): void
    {
        $this->tokenAnfordern();

        $antwort = $this->zuruecksetzen(['pwtoken' => $token], $json);

        $this->assertLessThan(500, $antwort->getStatusCode());
    }

    // AK7 (Zahl 0 ohne angefordertes Token: MariaDB-Typumwandlung darf nichts aufschließen)
    public function test_zahl_null_als_token_ohne_angeforderten_reset_wird_abgelehnt(): void
    {
        $antwort = $this->zuruecksetzen(['pwtoken' => 0], true);

        $this->assertAbgelehnt($antwort, true);
        $this->assertTrue($this->passwortUnveraendert());
    }

    // AK7
    public function test_token_einer_anderen_email_wird_abgelehnt(): void
    {
        $andere = User::factory()->create(['email' => 'andere@firma.de']);
        $token = $this->tokenAnfordern();

        $antwort = $this->zuruecksetzen(['pwtoken' => $token, 'email' => $andere->email]);

        $this->assertAbgelehnt($antwort, false);
        $this->assertTrue($this->passwortUnveraendert($andere));
        $this->assertTrue($this->passwortUnveraendert());
    }

    // AK7
    public function test_token_mit_unbekannter_email_wird_abgelehnt(): void
    {
        $token = $this->tokenAnfordern();

        $antwort = $this->zuruecksetzen(['pwtoken' => $token, 'email' => 'niemand@example.org']);

        $this->assertAbgelehnt($antwort, false);
        $this->assertTrue($this->passwortUnveraendert());
    }

    // AK7
    public function test_nach_61_minuten_ist_das_token_abgelaufen(): void
    {
        $token = $this->tokenAnfordern();
        $this->travel(61)->minutes();

        $antwort = $this->zuruecksetzen(['pwtoken' => $token]);

        $this->assertAbgelehnt($antwort, false);
        $this->assertTrue($this->passwortUnveraendert());
    }

    // AK7 (Gegenprobe zur Grenze: Standard 60 Minuten)
    public function test_nach_59_minuten_ist_das_token_noch_gueltig(): void
    {
        $token = $this->tokenAnfordern();
        $this->travel(59)->minutes();

        $this->zuruecksetzen(['pwtoken' => $token])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEUES_PASSWORT, $this->nutzer->fresh()->password));
    }

    // AK7 ("konfigurierbar" – Annahme: Standard-Schlüssel des Laravel-Password-Brokers)
    public function test_ablaufzeit_ist_ueber_die_broker_konfiguration_einstellbar(): void
    {
        config()->set('auth.passwords.users.expire', 5);
        $token = $this->tokenAnfordern();
        $this->travel(6)->minutes();

        $antwort = $this->zuruecksetzen(['pwtoken' => $token]);

        $this->assertAbgelehnt($antwort, false);
        $this->assertTrue($this->passwortUnveraendert());
    }

    // AK7 (Randfall: Reset-Tokens aus der Zeit vor dem Update sind ungültig)
    public function test_altes_token_aus_der_users_tabelle_wird_abgelehnt(): void
    {
        DB::table('users')->where('id', $this->nutzer->id)->update([
            'edit_pw_token' => 'AltesToken1',
            'edit_pw_token_end' => now()->addMinutes(20),
        ]);
        $this->nutzer = $this->nutzer->fresh();

        $antwort = $this->zuruecksetzen(['pwtoken' => 'AltesToken1']);

        $this->assertAbgelehnt($antwort, false);
        $this->assertTrue($this->passwortUnveraendert());
    }

    // ------------------------------------------------------------------- AK8

    // AK8
    public function test_pro_email_geht_hoechstens_ein_link_pro_60_sekunden_raus(): void
    {
        $this->anfordern(self::GESPEICHERTE_ADRESSE);
        $zweite = $this->anfordern(self::GESPEICHERTE_ADRESSE);

        $this->assertLessThan(500, $zweite->getStatusCode());
        $this->assertCount(1, $this->nachrichtenAn(self::GESPEICHERTE_ADRESSE));
    }

    // AK8
    public function test_nach_60_sekunden_darf_wieder_ein_link_verschickt_werden(): void
    {
        $this->anfordern(self::GESPEICHERTE_ADRESSE);
        $this->travel(61)->seconds();

        $this->anfordern(self::GESPEICHERTE_ADRESSE);

        $this->assertCount(2, $this->nachrichtenAn(self::GESPEICHERTE_ADRESSE));
    }

    // AK8
    public function test_abweichende_schreibweise_umgeht_die_email_drosselung_nicht(): void
    {
        $this->anfordern(self::GESPEICHERTE_ADRESSE);
        $this->anfordern('max.muster@firma.de');

        $this->assertCount(1, $this->gesendeteNachrichten());
    }

    // AK8
    public function test_die_email_drosselung_gilt_nicht_fuer_andere_adressen(): void
    {
        User::factory()->create(['email' => 'zweite@firma.de']);

        $this->anfordern(self::GESPEICHERTE_ADRESSE);
        $this->anfordern('zweite@firma.de');

        $this->assertCount(1, $this->nachrichtenAn(self::GESPEICHERTE_ADRESSE));
        $this->assertCount(1, $this->nachrichtenAn('zweite@firma.de'));
    }

    // AK8
    public function test_anfordern_ist_je_ip_auf_5_anfragen_pro_minute_begrenzt(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertNotSame(429, $this->anfordern("niemand{$i}@example.org")->getStatusCode(), "Anfrage {$i} darf nicht gedrosselt werden.");
        }

        $this->anfordern('niemand6@example.org')->assertStatus(429);
    }

    // AK8
    public function test_die_ip_drosselung_beim_anfordern_gilt_nur_fuer_diese_ip(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->anfordern("niemand{$i}@example.org");
        }

        $andere = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->anfordern('niemand7@example.org');

        $this->assertNotSame(429, $andere->getStatusCode());
    }

    // AK8
    public function test_die_ip_drosselung_beim_anfordern_endet_nach_einer_minute(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->anfordern("niemand{$i}@example.org");
        }
        $this->travel(61)->seconds();

        $this->assertNotSame(429, $this->anfordern('niemand7@example.org')->getStatusCode());
    }

    // AK8
    public function test_zuruecksetzen_ist_je_ip_auf_5_anfragen_pro_minute_begrenzt(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertNotSame(429, $this->zuruecksetzen(['pwtoken' => "falsch{$i}"], true)->getStatusCode(), "Anfrage {$i} darf nicht gedrosselt werden.");
        }

        $this->zuruecksetzen(['pwtoken' => 'falsch6'], true)->assertStatus(429);
    }

    // AK8
    public function test_gedrosselte_zuruecksetzen_anfrage_aendert_das_passwort_nicht_auch_mit_gueltigem_token(): void
    {
        $token = $this->tokenAnfordern();
        for ($i = 1; $i <= 5; $i++) {
            $this->zuruecksetzen(['pwtoken' => "falsch{$i}"], true);
        }

        $this->zuruecksetzen(['pwtoken' => $token], true)->assertStatus(429);

        $this->assertTrue($this->passwortUnveraendert());
    }
}
