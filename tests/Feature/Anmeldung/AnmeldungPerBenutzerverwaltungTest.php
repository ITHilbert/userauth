<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Anmeldung;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Feature\SocialLogin\SocialLoginTestCase;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\Support\AnmeldungPerHilfen;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Benutzerverwaltung je Modus (userauth-anmeldung-per, #1311): AK12 und die Modus-email-Gegenprobe aus AK2.
 * Aufbau: Social-Login-Testaufbau (Rollen 1–3); der Admin hat Rolle 3 mit user_create und user_edit.
 */
final class AnmeldungPerBenutzerverwaltungTest extends SocialLoginTestCase
{
    use AnmeldungPerHilfen;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grundeinstellung('benutzername');
        $this->benutzernameSpalteAnlegen();
        $this->emailIndexEntfernen();
        config()->set('userauth.geloeschte_email', 'sperren');
        foreach (['user_create', 'user_edit'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('role_permission')->insert(['role_id' => 3, 'permission_id' => $i + 1]);
        }
        $this->admin = User::factory()->create(['role_id' => 3, 'email' => 'admin@example.test', 'benutzername' => 'admin.x']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function benutzernameModi(): array
    {
        return ['benutzername' => ['benutzername'], 'beides' => ['beides']];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function daten(array $extra = []): array
    {
        return array_merge([
            'name' => 'Neuer Name',
            'email' => 'neu@example.test',
            'password' => 'Neues-Passwort-2026!',
            'password2' => 'Neues-Passwort-2026!',
            'role_id' => 3,
            'benutzername' => 'neu.k',
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $daten
     */
    private function anlegen(array $daten): TestResponse
    {
        return $this->actingAs($this->admin)->from(route('user.create'))->post(route('user.store'), $daten);
    }

    /**
     * @param  array<string, mixed>  $daten
     */
    private function bearbeiten(User $ziel, array $daten): TestResponse
    {
        return $this->actingAs($this->admin)->from(route('user.edit', $ziel->id))->post(route('user.update', $ziel->id), $daten);
    }

    private function zeilen(): int
    {
        return User::withTrashed()->count();
    }

    private function benutzernameVon(int $id): ?string
    {
        return DB::table('users')->where('id', $id)->value('benutzername');
    }

    // AK12 / AK2
    #[DataProvider('benutzernameModi')]
    public function test_ak12_formulare_zeigen_das_pflichtfeld_benutzername_nur_in_den_modi_benutzername_und_beides(string $modus): void
    {
        $ziel = User::factory()->create(['benutzername' => 'ziel.k', 'email' => 'ziel@example.test']);

        foreach (['email', $modus] as $aktuell) {
            $this->modus($aktuell);
            foreach ([route('user.create'), route('user.edit', $ziel->id)] as $seite) {
                $feld = $this->eingabe((string) $this->actingAs($this->admin)->get($seite)->assertOk()->getContent(), 'benutzername');
                if ($aktuell === 'email') {
                    $this->assertNull($feld, "Modus email: kein Feld benutzername auf {$seite}");

                    continue;
                }
                $this->assertNotNull($feld, "Modus {$aktuell}: Feld benutzername fehlt auf {$seite}");
                $this->assertTrue($feld->hasAttribute('required'), 'Pflichtfeld');
                if (str_contains($seite, 'edit')) {
                    $this->assertSame('ziel.k', $feld->getAttribute('value'));
                }
            }
        }
    }

    // AK12
    #[DataProvider('benutzernameModi')]
    public function test_ak12_anlegen_speichert_den_bereinigten_benutzername_und_erlaubt_eine_gemeinsame_email(string $modus): void
    {
        $this->modus($modus);
        $bestehend = User::factory()->create(['benutzername' => 'alt.k', 'email' => 'gem@example.test']);

        $this->anlegen($this->daten(['benutzername' => "  neu.k \t", 'email' => 'GEM@example.test']))
            ->assertSessionHasNoErrors()->assertRedirect(route('user'));
        $this->anlegen($this->daten(['benutzername' => 'zwei.k', 'email' => 'gem@example.test']))->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('benutzername', 'neu.k')->count(), 'Benutzername ohne Randleerzeichen gespeichert');
        $this->assertSame(3, DB::table('users')->whereRaw('lower(email) = ?', ['gem@example.test'])->count(), 'Drei Konten teilen die E-Mail');
        $this->assertSame('alt.k', $this->benutzernameVon($bestehend->id));
    }

    // AK12
    #[DataProvider('benutzernameModi')]
    public function test_ak12_benutzername_ist_pflicht_eindeutig_hoechstens_255_zeichen_und_ohne_at(string $modus): void
    {
        $this->modus($modus);
        User::factory()->create(['benutzername' => 'anna.m', 'email' => 'anna@example.test']);
        User::factory()->create(['benutzername' => 'weg.x', 'email' => 'weg@example.test'])->delete();
        $vorher = $this->zeilen();
        $abgelehnt = [
            'fehlt' => null, 'leer' => '', 'nur Leerzeichen' => '   ', 'vergeben' => 'anna.m', 'vergeben (Schreibweise)' => ' anna.m ',
            'vergeben bei gelöschtem Konto' => 'weg.x', '256 Zeichen' => str_repeat('a', 256), 'wie eine E-Mail' => 'anna@example.test', 'mit at' => 'a@b',
        ];

        foreach ($abgelehnt as $name => $wert) {
            $daten = $this->daten(['email' => 'x'.md5($name).'@example.test']);
            if ($wert === null) {
                unset($daten['benutzername']);
            } else {
                $daten['benutzername'] = $wert;
            }
            $this->anlegen($daten)->assertSessionHasErrors('benutzername');
            $this->assertSame($vorher, $this->zeilen(), "Fall «{$name}» hat ein Konto angelegt");
        }

        // Gegenprobe: genau 255 Zeichen sind erlaubt
        $this->anlegen($this->daten(['benutzername' => str_repeat('b', 255)]))->assertSessionHasNoErrors();
        $this->assertSame($vorher + 1, $this->zeilen());
    }

    // AK12 (kein Schrägstrich: er bräche den Reset-Link password/editwithtoken/{token}/{kennung})
    #[DataProvider('benutzernameModi')]
    public function test_ak12_benutzername_darf_keinen_schraegstrich_enthalten(string $modus): void
    {
        $this->modus($modus);
        $ziel = User::factory()->create(['benutzername' => 'ziel.k', 'email' => 'ziel@example.test']);
        $vorher = $this->zeilen();
        $basis = ['name' => 'Name', 'role_id' => 3, 'email' => 'ziel@example.test'];

        $this->anlegen($this->daten(['benutzername' => 'anna/m']))->assertSessionHasErrors('benutzername');
        $this->assertSame($vorher, $this->zeilen(), 'Anlegen mit Schrägstrich legt kein Konto an');
        $this->bearbeiten($ziel, array_merge($basis, ['benutzername' => 'anna/m']))->assertSessionHasErrors('benutzername');
        $this->assertSame('ziel.k', $this->benutzernameVon($ziel->id), 'Bearbeiten lässt den Wert unverändert');

        // Gegenprobe: derselbe Aufbau mit Punkt statt Schrägstrich wird gespeichert
        $this->anlegen($this->daten(['benutzername' => 'anna.m']))->assertSessionHasNoErrors();
        $this->assertSame($vorher + 1, $this->zeilen());
        $this->bearbeiten($ziel, array_merge($basis, ['benutzername' => 'bert.m']))->assertSessionHasNoErrors();
        $this->assertSame('bert.m', $this->benutzernameVon($ziel->id));
    }

    // AK12
    #[DataProvider('benutzernameModi')]
    public function test_ak12_email_bleibt_pflicht_und_muss_gueltig_sein(string $modus): void
    {
        $this->modus($modus);
        $vorher = $this->zeilen();

        $this->anlegen($this->daten(['email' => '']))->assertSessionHasErrors('email');
        $this->anlegen($this->daten(['email' => 'keine-email']))->assertSessionHasErrors('email');
        $this->assertSame($vorher, $this->zeilen());

        $this->anlegen($this->daten())->assertSessionHasNoErrors(); // Gegenprobe
        $this->assertSame($vorher + 1, $this->zeilen());
    }

    // AK12
    #[DataProvider('benutzernameModi')]
    public function test_ak12_die_sperre_fuer_emails_geloeschter_benutzer_entfaellt_in_diesen_modi(string $modus): void
    {
        $geloescht = User::factory()->create(['benutzername' => 'alt.k', 'email' => 'alt@example.test']);
        $geloescht->delete();

        // Gegenprobe: im Modus email sperrt die Option „sperren“ weiter
        $this->modus('email');
        $this->anlegen($this->daten(['email' => 'alt@example.test']))->assertSessionHasErrors('email');
        $this->assertSame(1, $this->zeilen() - 1, 'nur Admin und das gelöschte Konto');

        foreach (['sperren', 'reaktivieren', 'neu'] as $option) {
            config()->set('userauth.geloeschte_email', $option);
            $this->modus($modus);
            $this->anlegen($this->daten(['email' => 'alt@example.test', 'benutzername' => 'neu.'.$option]))->assertSessionHasNoErrors();

            $neu = User::where('benutzername', 'neu.'.$option)->firstOrFail();
            $this->assertNotSame($geloescht->id, $neu->id, "Option {$option}: neue Zeile");
            $alt = User::withTrashed()->find($geloescht->id);
            $this->assertNotNull($alt, "Option {$option}: das gelöschte Konto bleibt bestehen");
            $this->assertNotNull($alt->deleted_at, "Option {$option}: das gelöschte Konto wird nicht wiederhergestellt");
            $this->assertSame('alt.k', $alt->benutzername);
        }
    }

    // AK12
    #[DataProvider('benutzernameModi')]
    public function test_ak12_bearbeiten_prueft_benutzername_ohne_email_eindeutigkeit(string $modus): void
    {
        $this->modus($modus);
        $a = User::factory()->create(['benutzername' => 'a.eins', 'email' => 'a@example.test']);
        $b = User::factory()->create(['benutzername' => 'b.zwei', 'email' => 'b@example.test']);
        $basis = ['name' => 'Name', 'role_id' => 3];

        $this->bearbeiten($b, array_merge($basis, ['email' => 'a@example.test', 'benutzername' => 'b.zwei']))->assertSessionHasNoErrors();
        $this->assertSame('a@example.test', $b->fresh()->email, 'Gemeinsame E-Mail erlaubt, eigener Benutzername zählt nicht als vergeben');

        $this->bearbeiten($b, array_merge($basis, ['email' => 'b@example.test', 'benutzername' => 'a.eins']))->assertSessionHasErrors('benutzername');
        $this->bearbeiten($b, array_merge($basis, ['email' => 'b@example.test']))->assertSessionHasErrors('benutzername');
        $this->bearbeiten($b, array_merge($basis, ['email' => 'b@example.test', 'benutzername' => 'x@y']))->assertSessionHasErrors('benutzername');
        $this->assertSame('b.zwei', $this->benutzernameVon($b->id));

        $this->bearbeiten($b, array_merge($basis, ['email' => 'b@example.test', 'benutzername' => ' b.neu ']))->assertSessionHasNoErrors();
        $this->assertSame('b.neu', $this->benutzernameVon($b->id));
        $this->assertSame('a.eins', $this->benutzernameVon($a->id));
    }

    // AK2 / AK12
    public function test_ak2_modus_email_prueft_die_email_wie_bisher_und_laesst_den_benutzernamen_in_ruhe(): void
    {
        $this->modus('email');
        $a = User::factory()->create(['benutzername' => 'keep.me', 'email' => 'a@example.test']);
        $b = User::factory()->create(['benutzername' => 'b.zwei', 'email' => 'b@example.test']);

        $this->anlegen($this->daten(['email' => 'a@example.test']))->assertSessionHasErrors('email');
        $this->bearbeiten($b, ['name' => 'Name', 'role_id' => 3, 'email' => 'a@example.test'])->assertSessionHasErrors('email');

        // Kein Pflichtfeld benutzername: Anlegen und Bearbeiten ohne das Feld gehen, gespeicherte Werte bleiben
        $this->anlegen($this->daten(['benutzername' => null]))->assertSessionHasNoErrors();
        $this->bearbeiten($a, ['name' => 'Anderer Name', 'role_id' => 3, 'email' => 'a@example.test'])->assertSessionHasNoErrors();
        $this->assertSame('keep.me', $this->benutzernameVon($a->id));
        $this->assertSame('Anderer Name', $a->fresh()->name);
    }

    // AK12
    public function test_ak12_benutzername_ist_ueber_userauth_fuellbar(): void
    {
        $this->assertTrue((new User)->isFillable('benutzername'));
        $this->assertTrue((new User)->isFillable('email'), 'Gegenprobe: Bestandsfelder bleiben füllbar');
        $this->assertFalse((new User)->isFillable('id'));
    }

    // AK12 (Struktur der Schema-Dateien, die Hosts für ihre Formulare lesen)
    #[DataProvider('schemaDateien')]
    public function test_ak12_schema_json_hat_ein_pflichtfeld_benutzername(string $datei): void
    {
        $schema = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/src/Resources/schema/ui/user/'.$datei), true, flags: JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('email', $schema['dataSchema'], 'Gegenprobe: Bestandsfeld vorhanden');
        $this->assertTrue($schema['dataSchema']['benutzername']['required'] ?? false);
        $this->assertContains('benutzername', array_column($schema['uiSchema']['elements'], 'field'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function schemaDateien(): array
    {
        return ['create.json' => ['create.json'], 'edit.json' => ['edit.json']];
    }
}
