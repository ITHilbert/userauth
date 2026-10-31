<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Höchstlänge von `role` und `role_display` im Rollenformular (userauth-rolle-laenge, Dashboard #371).
 * Abgedeckt: AK1–AK3 und der Randfall (Mehrbyte-Zeichen).
 */
final class RolleLaengeTest extends TestCase
{
    use RefreshDatabase;

    private const MAX = 255;

    private const ROLLE_NUTZER = 3;

    private const ROLLE_ZIEL = 4;

    /** @var array<string, int> */
    private array $rechteIds = [];

    private int $naechsteRechteId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');

        $jetzt = now();
        $rollen = [
            [1, 'dev', 'Dev'],
            [2, 'admin', 'Admin'],
            [self::ROLLE_NUTZER, 'user', 'User'],
            [self::ROLLE_ZIEL, 'editor', 'Editor'],
        ];
        foreach ($rollen as [$id, $schluessel, $anzeige]) {
            DB::table('roles')->insert([
                'id' => $id, 'role' => $schluessel, 'role_display' => $anzeige,
                'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => null,
            ]);
        }

        $this->gruppe(1, 'user');
        $this->gruppe(2, 'role');

        foreach (['role_create', 'role_read', 'role_edit'] as $recht) {
            DB::table('role_permission')->insert([
                'role_id' => self::ROLLE_NUTZER,
                'permission_id' => $this->rechteIds[$recht],
            ]);
        }
        DB::table('role_permission')->insert([
            'role_id' => self::ROLLE_ZIEL, 'permission_id' => $this->rechteIds['user_edit'],
        ]);
    }

    protected function tearDown(): void
    {
        Model::clearBootedModels();

        parent::tearDown();
    }

    private function gruppe(int $id, string $name): void
    {
        $jetzt = now();
        DB::table('permissions_groups')->insert([
            'id' => $id, 'group_name' => $name, 'group_display' => 'Gruppe '.$name, 'is_group' => 0,
            'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);

        foreach (['create', 'read', 'edit', 'delete'] as $art) {
            $recht = $name.'_'.$art;
            $rechtId = $this->naechsteRechteId++;
            DB::table('permissions')->insert([
                'id' => $rechtId, 'permission' => $recht, 'permission_display' => 'Recht '.$recht,
                'group_id' => $id, 'crud' => $art, 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            $this->rechteIds[$recht] = $rechtId;
        }
    }

    private function nutzer(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_NUTZER]);
    }

    /**
     * @return list<int>
     */
    private function rollenRechte(int $rolleId): array
    {
        $ids = DB::table('role_permission')->where('role_id', $rolleId)->pluck('permission_id')
            ->map(fn ($i): int => (int) $i)->all();
        sort($ids);

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function rolleZeile(int $rolleId): array
    {
        return (array) DB::table('roles')->where('id', $rolleId)->first();
    }

    /**
     * @param  array<string, mixed>  $abweichung
     * @return array<string, mixed>
     */
    private function daten(array $abweichung = []): array
    {
        return array_merge([
            'role_display' => 'Editor',
            'role' => 'editor',
            'permission' => [$this->rechteIds['user_read']],
        ], $abweichung);
    }

    /**
     * Gültige Daten für Anlegen (frischer Schlüssel und Anzeigename) bzw. Bearbeiten, mit einem abweichenden Feld.
     *
     * @return array<string, mixed>
     */
    private function datenMit(bool $anlegen, string $feld, string $wert): array
    {
        $basis = $anlegen ? ['role_display' => 'Neu B', 'role' => 'neub'] : [];

        return $this->daten([...$basis, $feld => $wert]);
    }

    /**
     * @param  array<string, mixed>  $daten
     * @return TestResponse<Response>
     */
    private function senden(bool $anlegen, array $daten)
    {
        $nutzer = $this->nutzer();

        return $anlegen
            ? $this->actingAs($nutzer)->from(route('role.create'))->post(route('role.store'), $daten)
            : $this->actingAs($nutzer)->from(route('role.edit', self::ROLLE_ZIEL))
                ->post(route('role.update', self::ROLLE_ZIEL), $daten);
    }

    /** Beweist, dass der Aufbau greift: gültiges Anlegen bzw. Bearbeiten wird gespeichert. */
    private function gegenprobe(bool $anlegen): void
    {
        if ($anlegen) {
            $this->senden(true, $this->daten(['role_display' => 'Neu A', 'role' => 'neu a']))
                ->assertSessionHasNoErrors()->assertRedirect(route('role'));
            $this->assertSame(1, DB::table('roles')->where('role', 'neua')->count());

            return;
        }

        $this->senden(false, $this->daten(['permission' => [$this->rechteIds['role_read']]]))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));
        $this->assertSame([$this->rechteIds['role_read']], $this->rollenRechte(self::ROLLE_ZIEL));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function feldUndWeg(): array
    {
        // [Feld, Anlegen (sonst Bearbeiten)]
        return [
            'role_display beim Anlegen' => ['role_display', true],
            'role beim Anlegen' => ['role', true],
            'role_display beim Bearbeiten' => ['role_display', false],
            'role beim Bearbeiten' => ['role', false],
        ];
    }

    private function andereFelder(string $feld): string
    {
        return $feld === 'role' ? 'role_display' : 'role';
    }

    // ------------------------------------------------------------------ AK1

    // AK1
    #[DataProvider('feldUndWeg')]
    public function test_zu_langer_wert_scheitert_am_feld_ohne_5xx(string $feld, bool $anlegen): void
    {
        $this->gegenprobe($anlegen);
        $wert = str_repeat('a', self::MAX + 1);

        $antwort = $this->senden($anlegen, $this->datenMit($anlegen, $feld, $wert));

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors($feld);
        $antwort->assertSessionDoesntHaveErrors($this->andereFelder($feld));
    }

    // AK1
    #[DataProvider('feldUndWeg')]
    public function test_zu_langer_wert_speichert_nichts(string $feld, bool $anlegen): void
    {
        $this->gegenprobe($anlegen);
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);
        $rechteVorher = $this->rollenRechte(self::ROLLE_ZIEL);
        $rollenVorher = DB::table('roles')->count();
        $zuordnungenVorher = DB::table('role_permission')->count();
        $wert = str_repeat('a', self::MAX + 1);
        $daten = $this->datenMit($anlegen, $feld, $wert);
        $daten['permission'] = [$this->rechteIds['role_edit']];

        $this->senden($anlegen, $daten);

        $this->assertSame($rollenVorher, DB::table('roles')->count());
        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
        $this->assertSame($rechteVorher, $this->rollenRechte(self::ROLLE_ZIEL));
        $this->assertSame($zuordnungenVorher, DB::table('role_permission')->count());
        $this->assertSame(0, DB::table('roles')->where($feld, $wert)->count());
    }

    // ------------------------------------------------------------------ AK2

    // AK2
    #[DataProvider('feldUndWeg')]
    public function test_genau_255_zeichen_werden_gespeichert(string $feld, bool $anlegen): void
    {
        $wert = str_repeat('a', self::MAX);

        $this->senden($anlegen, $this->datenMit($anlegen, $feld, $wert))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame(1, DB::table('roles')->where($feld, $wert)->count());
        $this->assertSame($wert, DB::table('roles')->where($feld, $wert)->value($feld));
    }

    // ------------------------------------------------------------------ AK3

    /**
     * @return array<string, array{0: bool}>
     */
    public static function wege(): array
    {
        return ['Anlegen' => [true], 'Bearbeiten' => [false]];
    }

    // AK3
    #[DataProvider('wege')]
    public function test_roher_schluessel_ueber_255_der_formatiert_255_hat_wird_gespeichert(bool $anlegen): void
    {
        // roh 265 Zeichen: Leerzeichen, Semikolon und Spitzklammern entfallen beim Formatieren
        $roh = str_repeat('A', self::MAX).' ; < > ;';
        $this->assertGreaterThan(self::MAX, mb_strlen($roh));

        $this->senden($anlegen, $this->datenMit($anlegen, 'role', $roh))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame(1, DB::table('roles')->where('role', str_repeat('a', self::MAX))->count());
    }

    // AK3
    #[DataProvider('wege')]
    public function test_roher_schluessel_unter_255_der_formatiert_laenger_wird_scheitert_am_feld_role(bool $anlegen): void
    {
        $this->gegenprobe($anlegen);
        $roh = str_repeat('ä', 128); // roh 128, formatiert 'ae' × 128 = 256
        $this->assertLessThan(self::MAX, mb_strlen($roh));

        $antwort = $this->senden($anlegen, $this->datenMit($anlegen, 'role', $roh));

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('role');
        $antwort->assertSessionDoesntHaveErrors('role_display');
        $this->assertSame(0, DB::table('roles')->where('role', str_repeat('ae', 128))->count());
    }

    // AK3
    #[DataProvider('wege')]
    public function test_roher_schluessel_unter_255_der_formatiert_genau_255_hat_wird_gespeichert(bool $anlegen): void
    {
        $roh = str_repeat('ä', 127).'a'; // formatiert 254 + 1 = 255
        $erwartet = str_repeat('ae', 127).'a';
        $this->assertSame(self::MAX, strlen($erwartet));

        $this->senden($anlegen, $this->datenMit($anlegen, 'role', $roh))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame(1, DB::table('roles')->where('role', $erwartet)->count());
    }

    // AK3
    #[DataProvider('wege')]
    public function test_roher_schluessel_ueber_255_der_formatiert_256_hat_wird_abgelehnt(bool $anlegen): void
    {
        $this->gegenprobe($anlegen);
        $roh = str_repeat('a', self::MAX + 1).' ';
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);
        $rollenVorher = DB::table('roles')->count();
        $this->assertSame(self::MAX + 2, strlen($roh));

        $this->senden($anlegen, $this->datenMit($anlegen, 'role', $roh))->assertSessionHasErrors('role');

        $this->assertSame($rollenVorher, DB::table('roles')->count());
        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
    }

    // ------------------------------------------------------------------ Randfälle

    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function mehrbyteFelder(): array
    {
        // [Feld, Anlegen, Zeichen]
        return [
            'role_display ä beim Anlegen' => ['role_display', true, 'ä'],
            'role_display ä beim Bearbeiten' => ['role_display', false, 'ä'],
            'role é beim Anlegen' => ['role', true, 'é'],
            'role é beim Bearbeiten' => ['role', false, 'é'],
        ];
    }

    // Randfall 1
    #[DataProvider('mehrbyteFelder')]
    public function test_mehrbyte_zeichen_zaehlen_einzeln_255_werden_gespeichert(string $feld, bool $anlegen, string $zeichen): void
    {
        $wert = str_repeat($zeichen, self::MAX);
        $this->assertGreaterThan(self::MAX, strlen($wert));

        $this->senden($anlegen, $this->datenMit($anlegen, $feld, $wert))
            ->assertSessionHasNoErrors()->assertRedirect(route('role'));

        $this->assertSame($wert, DB::table('roles')->where($feld, $wert)->value($feld));
    }

    // Randfall 1
    #[DataProvider('mehrbyteFelder')]
    public function test_mehrbyte_zeichen_256_scheitern_am_feld(string $feld, bool $anlegen, string $zeichen): void
    {
        $this->gegenprobe($anlegen);
        $wert = str_repeat($zeichen, self::MAX + 1);
        $zeileVorher = $this->rolleZeile(self::ROLLE_ZIEL);
        $rollenVorher = DB::table('roles')->count();

        $antwort = $this->senden($anlegen, $this->datenMit($anlegen, $feld, $wert));

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors($feld);
        $antwort->assertSessionDoesntHaveErrors($this->andereFelder($feld));
        $this->assertSame($rollenVorher, DB::table('roles')->count());
        $this->assertSame($zeileVorher, $this->rolleZeile(self::ROLLE_ZIEL));
    }
}
