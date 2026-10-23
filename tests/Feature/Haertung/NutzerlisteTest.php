<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Haertung;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use ITHilbert\UserAuth\Traits\UserAuth;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Nutzerliste und Serialisierung des User-Models. Abgedeckt: AK1, AK2, AK3 (userauth-haertung).
 */
final class NutzerlisteTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    private const OPFER_EMAIL = 'opfer.geheim@example.test';

    /** Werte, die niemals in einer Antwort oder Serialisierung auftauchen dürfen. */
    private const GEHEIME_WERTE = [
        'HASH-ANFANG' => '92IXUNpkjO0rOQ5byMi',
        'REMEMBER' => 'REMEMBER-TOKEN-WERT-123',
        'RESET' => 'RESET-TOKEN-WERT-456',
        'ZWEIFAKTOR' => 'ZF-CODE-WERT-789',
    ];

    /** Schlüssel, die in keiner Zeile der Liste vorkommen dürfen (AK2). */
    private const VERBOTENE_SCHLUESSEL = [
        'password',
        'remember_token',
        'edit_pw_token',
        'edit_pw_token_end',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Host-Layout und -Includes, die die Paket-Views voraussetzen (im Host vorhanden, hier nur Platzhalter)
        View::addLocation(__DIR__.'/../../Fixtures/views');

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => self::ROLLE_NORMAL, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
        foreach (['user_read', 'user_create'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function anfrageArten(): array
    {
        return [
            'normaler Aufruf' => [false],
            'Ajax-Aufruf' => [true],
        ];
    }

    private function nutzerMitRecht(?string $recht): User
    {
        $user = User::factory()->create(['role_id' => self::ROLLE_NORMAL]);
        if ($recht !== null) {
            DB::table('role_permission')->insert([
                'role_id' => self::ROLLE_NORMAL,
                'permission_id' => DB::table('permissions')->where('permission', $recht)->value('id'),
            ]);
        }

        return $user;
    }

    /**
     * Anderer Nutzer mit allen sensiblen Spalten gefüllt.
     */
    private function opferMitGeheimdaten(): User
    {
        $opfer = User::factory()->create([
            'email' => self::OPFER_EMAIL,
            'role_id' => self::ROLLE_NORMAL,
            'remember_token' => self::GEHEIME_WERTE['REMEMBER'],
        ]);
        DB::table('users')->where('id', $opfer->id)->update([
            'edit_pw_token' => self::GEHEIME_WERTE['RESET'],
            'edit_pw_token_end' => now()->addHour(),
            'two_factor_code' => self::GEHEIME_WERTE['ZWEIFAKTOR'],
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        return $opfer;
    }

    /**
     * @return array<string, string>
     */
    private function kopfzeilen(bool $ajax): array
    {
        return $ajax ? ['X-Requested-With' => 'XMLHttpRequest'] : [];
    }

    // ---------------------------------------------------------------- AK1

    // AK1
    #[DataProvider('anfrageArten')]
    public function test_nutzerliste_ohne_user_read_liefert_keine_nutzerdaten(bool $ajax): void
    {
        $this->opferMitGeheimdaten();
        $user = $this->nutzerMitRecht(null);

        $response = $this->actingAs($user)->withHeaders($this->kopfzeilen($ajax))->get('/users');

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertLessThan(500, $response->getStatusCode());
        $this->assertStringNotContainsString(self::OPFER_EMAIL, (string) $response->getContent());
    }

    // AK1
    #[DataProvider('anfrageArten')]
    public function test_nutzerliste_ohne_user_read_wird_wie_jedes_andere_fehlende_recht_abgelehnt(bool $ajax): void
    {
        $user = $this->nutzerMitRecht(null);
        $kopfzeilen = $this->kopfzeilen($ajax);

        // Vergleichsfall: /users/create ist per hasPermission:user_create geschützt, dem Nutzer fehlt auch dieses Recht.
        $vergleich = $this->actingAs($user)->withHeaders($kopfzeilen)->get('/users/create');
        $liste = $this->actingAs($user)->withHeaders($kopfzeilen)->get('/users');

        $this->assertNotSame(200, $vergleich->getStatusCode(), 'Vorbedingung: fehlendes Recht wird abgelehnt');
        $this->assertSame($vergleich->getStatusCode(), $liste->getStatusCode());
        $this->assertSame($vergleich->headers->get('Location'), $liste->headers->get('Location'));
    }

    // AK1 (Gegenprobe, Regressionsschutz: mit user_read bleibt die Liste erreichbar)
    public function test_nutzerliste_mit_user_read_ist_per_ajax_erreichbar(): void
    {
        $this->opferMitGeheimdaten();
        $user = $this->nutzerMitRecht('user_read');

        $this->actingAs($user)->withHeaders($this->kopfzeilen(true))->get('/users')
            ->assertOk()
            ->assertSee(self::OPFER_EMAIL);
    }

    // AK1 (Gegenprobe, Regressionsschutz: mit user_read bleibt die Seite erreichbar)
    public function test_nutzerliste_mit_user_read_ist_im_normalen_aufruf_erreichbar(): void
    {
        $user = $this->nutzerMitRecht('user_read');

        $this->actingAs($user)->get('/users')->assertOk();
    }

    // ---------------------------------------------------------------- AK2

    // AK2
    public function test_nutzerliste_enthaelt_keine_sensiblen_schluessel(): void
    {
        $this->opferMitGeheimdaten();
        $user = $this->nutzerMitRecht('user_read');

        $response = $this->actingAs($user)->withHeaders($this->kopfzeilen(true))->get('/users')->assertOk();

        $zeilen = $response->json('data');
        $this->assertIsArray($zeilen);
        $this->assertNotEmpty($zeilen);
        $gefunden = false;
        foreach ($zeilen as $zeile) {
            $this->assertIsArray($zeile);
            foreach (self::VERBOTENE_SCHLUESSEL as $schluessel) {
                $this->assertArrayNotHasKey($schluessel, $zeile, "Zeile enthält den Schlüssel {$schluessel}");
            }
            $gefunden = $gefunden || ($zeile['email'] ?? null) === self::OPFER_EMAIL;
        }
        $this->assertTrue($gefunden, 'Die Zeile des anderen Nutzers (E-Mail) muss in der Liste stehen');
    }

    // AK2
    public function test_nutzerliste_enthaelt_keine_sensiblen_werte_in_der_antwort(): void
    {
        $this->opferMitGeheimdaten();
        $user = $this->nutzerMitRecht('user_read');

        $inhalt = (string) $this->actingAs($user)->withHeaders($this->kopfzeilen(true))->get('/users')->assertOk()->getContent();

        foreach (self::GEHEIME_WERTE as $name => $wert) {
            $this->assertStringNotContainsString($wert, $inhalt, "Antwort verrät den Wert {$name}");
        }
        foreach (self::VERBOTENE_SCHLUESSEL as $schluessel) {
            $this->assertStringNotContainsString('"'.$schluessel.'"', $inhalt, "Antwort enthält den Schlüssel {$schluessel}");
        }
    }

    // ---------------------------------------------------------------- AK3

    /**
     * Model, das ausschließlich den Trait nutzt – ohne eigenes $hidden wie das Fixture-Model.
     */
    private function nurTraitModel(): Model
    {
        $model = new class extends Model
        {
            use UserAuth;

            protected $table = 'users';
        };

        return $model->forceFill([
            'id' => 5,
            'name' => 'Trait Nutzer',
            'email' => 'trait@example.test',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/ig',
            'remember_token' => self::GEHEIME_WERTE['REMEMBER'],
            'two_factor_code' => self::GEHEIME_WERTE['ZWEIFAKTOR'],
            'two_factor_expires_at' => '2026-10-23 12:00:00',
            'edit_pw_token' => self::GEHEIME_WERTE['RESET'],
            'edit_pw_token_end' => '2026-10-23 13:00:00',
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function serialisierteSchluessel(): array
    {
        return [
            'password' => ['password'],
            'remember_token' => ['remember_token'],
            'two_factor_code' => ['two_factor_code'],
            'two_factor_expires_at' => ['two_factor_expires_at'],
            'edit_pw_token' => ['edit_pw_token'],
            'edit_pw_token_end' => ['edit_pw_token_end'],
        ];
    }

    // AK3
    #[DataProvider('serialisierteSchluessel')]
    public function test_trait_blendet_sensible_felder_in_to_array_aus(string $schluessel): void
    {
        $array = $this->nurTraitModel()->toArray();

        $this->assertSame('trait@example.test', $array['email'], 'Vorbedingung: normale Felder bleiben erhalten');
        $this->assertArrayNotHasKey($schluessel, $array);
    }

    // AK3
    #[DataProvider('serialisierteSchluessel')]
    public function test_trait_blendet_sensible_felder_in_to_json_aus(string $schluessel): void
    {
        $json = $this->nurTraitModel()->toJson();

        $this->assertStringContainsString('trait@example.test', $json);
        $this->assertArrayNotHasKey($schluessel, json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    // AK3
    public function test_trait_serialisiert_keine_sensiblen_werte(): void
    {
        $json = $this->nurTraitModel()->toJson();

        $this->assertStringNotContainsString(self::GEHEIME_WERTE['HASH-ANFANG'], $json);
        $this->assertStringNotContainsString(self::GEHEIME_WERTE['REMEMBER'], $json);
        $this->assertStringNotContainsString(self::GEHEIME_WERTE['ZWEIFAKTOR'], $json);
    }

    // AK3
    public function test_trait_serialisiert_den_reset_token_nicht_obwohl_er_gesetzt_ist(): void
    {
        $model = $this->nurTraitModel();

        // Gegenprobe: Die Felder sind am Model gesetzt, nur die Serialisierung blendet sie aus
        $this->assertSame(self::GEHEIME_WERTE['RESET'], $model->getAttributes()['edit_pw_token']);
        $this->assertSame('2026-10-23 13:00:00', $model->getAttributes()['edit_pw_token_end']);

        $json = $model->toJson();
        $this->assertStringContainsString('trait@example.test', $json);
        $this->assertStringNotContainsString(self::GEHEIME_WERTE['RESET'], $json);
        $this->assertStringNotContainsString('2026-10-23T13:00:00', $json);
        $this->assertStringNotContainsString('edit_pw_token', $json);
    }

    // AK3
    public function test_gespeichertes_user_model_serialisiert_den_reset_token_nicht_obwohl_er_in_der_db_steht(): void
    {
        $opfer = $this->opferMitGeheimdaten();
        $gespeichert = User::query()->findOrFail($opfer->id);

        // Gegenprobe: Werte stehen in der Datenbank und am geladenen Model
        $this->assertSame(self::GEHEIME_WERTE['RESET'], $gespeichert->getAttributes()['edit_pw_token']);
        $this->assertNotNull($gespeichert->getAttributes()['edit_pw_token_end']);

        $json = $gespeichert->toJson();
        $this->assertStringContainsString(self::OPFER_EMAIL, $json);
        $this->assertStringNotContainsString(self::GEHEIME_WERTE['RESET'], $json);
        $this->assertStringNotContainsString('edit_pw_token', $json);
    }

    // AK3
    #[DataProvider('serialisierteSchluessel')]
    public function test_gespeichertes_user_model_blendet_sensible_felder_aus(string $schluessel): void
    {
        $opfer = $this->opferMitGeheimdaten();

        $array = User::query()->findOrFail($opfer->id)->toArray();

        $this->assertArrayNotHasKey($schluessel, $array);
    }
}
