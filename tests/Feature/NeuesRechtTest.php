<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Neue Einzelrechte aus `permission_display_new_<n>` (userauth-neues-recht, Dashboard #381).
 * Gruppe A (id 3) ist eine bestehende Gruppe mit Einzelrechten (für `permission.update`);
 * `permission.store` legt eine neue Gruppe an.
 */
final class NeuesRechtTest extends TestCase
{
    use RefreshDatabase;

    private const GRUPPE_A = 3;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        $jetzt = now();
        foreach ([[1, 'dev', 'Dev'], [2, 'admin', 'Admin']] as [$id, $name, $anzeige]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => $anzeige, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }

        DB::table('permissions_groups')->insert([
            'id' => self::GRUPPE_A, 'group_name' => 'gruppe_a', 'group_display' => 'Gruppe A', 'is_group' => 1,
            'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);
        DB::table('permissions')->insert([
            'permission' => 'gruppe_a_a1', 'permission_display' => 'Recht A1', 'group_id' => self::GRUPPE_A,
            'crud' => 'single', 'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);

        $this->dev = User::factory()->create(['role_id' => 1, 'name' => 'Dev Nutzer', 'email' => 'dev@example.test']);
    }

    /**
     * @param  array<string, string>  $felder
     */
    private function store(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->post(
            route('permission.store'),
            array_merge(['group_display' => 'Neue Gruppe', 'group_name' => 'neue_gruppe', 'ckPermissionGroup' => 'on'], $felder)
        );
    }

    /**
     * @param  array<string, string>  $felder
     */
    private function update(array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->post(
            route('permission.update', self::GRUPPE_A),
            array_merge(['group_display' => 'Gruppe A', 'group_name' => 'gruppe_a'], $felder)
        );
    }

    /** @return list<array{permission: string, permission_display: string}> */
    private function neueRechte(int $groupId): array
    {
        return DB::table('permissions')->where('group_id', $groupId)->where('crud', 'single')
            ->whereNull('deleted_at')->orderBy('id')->get(['permission', 'permission_display'])
            ->map(fn ($z): array => (array) $z)->all();
    }

    private function neueGruppeId(): int
    {
        return (int) DB::table('permissions_groups')->where('group_display', 'Neue Gruppe')->value('id');
    }

    /** @return list<array{permission: string, permission_display: string}> */
    private function rechteNachStore(): array
    {
        return $this->neueRechte($this->neueGruppeId());
    }

    /** @return list<array{permission: string, permission_display: string}> */
    private function rechteNachUpdate(): array
    {
        // das bestehende Recht A1 ist nicht neu
        return array_values(array_filter(
            $this->neueRechte(self::GRUPPE_A),
            fn (array $r): bool => $r['permission'] !== 'gruppe_a_a1'
        ));
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function ohneInternenNamen(): array
    {
        return [
            'Feld fehlt' => [[]],
            'Feld leer' => [['permission_new_1' => '']],
        ];
    }

    // AK1
    #[DataProvider('ohneInternenNamen')]
    public function test_ak1_store_ohne_internen_namen_ergibt_keinen_5xx(array $intern): void
    {
        $antwort = $this->store(array_merge(['permission_display_new_1' => 'Möglich Neu: Test'], $intern));

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission'));
    }

    // AK1
    #[DataProvider('ohneInternenNamen')]
    public function test_ak1_store_ohne_internen_namen_nimmt_den_namen_aus_der_anzeige(array $intern): void
    {
        $this->store(array_merge(['permission_display_new_1' => 'Möglich Neu: Test'], $intern));

        $this->assertSame(
            [['permission' => 'moeglichneutest', 'permission_display' => 'Möglich Neu: Test']],
            $this->rechteNachStore()
        );
    }

    // AK1
    #[DataProvider('ohneInternenNamen')]
    public function test_ak1_update_ohne_internen_namen_ergibt_keinen_5xx(array $intern): void
    {
        $antwort = $this->update(array_merge(['permission_display_new_1' => 'Möglich Neu: Test'], $intern));

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission'));
    }

    // AK1
    #[DataProvider('ohneInternenNamen')]
    public function test_ak1_update_ohne_internen_namen_nimmt_den_namen_aus_der_anzeige(array $intern): void
    {
        $this->update(array_merge(['permission_display_new_1' => 'Möglich Neu: Test'], $intern));

        $this->assertSame(
            [['permission' => 'moeglichneutest', 'permission_display' => 'Möglich Neu: Test']],
            $this->rechteNachUpdate()
        );
    }

    // AK2
    public function test_ak2_store_gesendeter_name_gilt(): void
    {
        $this->store([
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'Mein Nämen: X',
        ])->assertRedirect(route('permission'));

        // Gegenprobe: der Name stammt nicht aus der Anzeige (die ergäbe „anzeigeeins“)
        $this->assertSame(
            [['permission' => 'meinnaemenx', 'permission_display' => 'Anzeige Eins']],
            $this->rechteNachStore()
        );
    }

    // AK2
    public function test_ak2_update_gesendeter_name_gilt(): void
    {
        $this->update([
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'Mein Nämen: X',
        ])->assertRedirect(route('permission'));

        $this->assertSame(
            [['permission' => 'meinnaemenx', 'permission_display' => 'Anzeige Eins']],
            $this->rechteNachUpdate()
        );
    }

    // AK2
    public function test_ak2_gesendete_und_fehlende_namen_gelten_je_recht_im_selben_post(): void
    {
        $this->update([
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'eigener_name',
            'permission_display_new_2' => 'Anzeige Zwei',
        ])->assertRedirect(route('permission'));

        $this->assertSame([
            ['permission' => 'eigener_name', 'permission_display' => 'Anzeige Eins'],
            ['permission' => 'anzeigezwei', 'permission_display' => 'Anzeige Zwei'],
        ], $this->rechteNachUpdate());
    }

    // AK3
    public function test_ak3_zugriff_auf_permission_new_steht_genau_einmal_im_controller(): void
    {
        $quelle = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/PermissionController.php');

        // Gegenprobe: die Datei ist die richtige und enthält die Anlage-Logik weiter
        $this->assertStringContainsString('permission_display_new', $quelle);
        $this->assertSame(1, substr_count($quelle, 'permission_new_'));
    }

    // AK2
    public function test_ak2_store_gesendete_und_fehlende_namen_gelten_je_recht_im_selben_post(): void
    {
        $this->store([
            'permission_display_new_1' => 'Anzeige Eins', 'permission_new_1' => 'eigener_name',
            'permission_display_new_2' => 'Anzeige Zwei',
        ])->assertRedirect(route('permission'));

        $this->assertSame([
            ['permission' => 'eigener_name', 'permission_display' => 'Anzeige Eins'],
            ['permission' => 'anzeigezwei', 'permission_display' => 'Anzeige Zwei'],
        ], $this->rechteNachStore());
    }

    // Randfall 1
    public function test_randfall1_store_mit_leerer_anzeige_legt_kein_recht_an(): void
    {
        $antwort = $this->store([
            'permission_display_new_1' => '', 'permission_new_1' => 'ohne_anzeige',
            'permission_display_new_2' => 'Zweites', 'permission_new_2' => 'zweites',
        ]);

        $antwort->assertRedirect(route('permission'));
        // Gegenprobe: das zweite neue Recht im selben POST wurde angelegt
        $this->assertSame(
            [['permission' => 'zweites', 'permission_display' => 'Zweites']],
            $this->rechteNachStore()
        );
    }

    // Randfall 1
    public function test_randfall1_update_mit_leerer_anzeige_legt_kein_recht_an(): void
    {
        $antwort = $this->update([
            'permission_display_new_1' => '', 'permission_new_1' => 'ohne_anzeige',
            'permission_display_new_2' => 'Zweites', 'permission_new_2' => 'zweites',
        ]);

        $antwort->assertRedirect(route('permission'));
        $this->assertSame(
            [['permission' => 'zweites', 'permission_display' => 'Zweites']],
            $this->rechteNachUpdate()
        );
    }

    // Randfall 1
    public function test_randfall1_leere_anzeige_ohne_internen_namen_ergibt_keinen_5xx(): void
    {
        $antwort = $this->update(['permission_display_new_1' => '']);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $this->assertSame([], $this->rechteNachUpdate());
    }
}
