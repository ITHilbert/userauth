<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Haertung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regressionstest zu AK13 „In keinem Fall entsteht ein Serverfehler“: Blade-Direktiven und
 * Middlewares mit null, Array mit null-Element, leerem Array, leerem Text und fehlenden Parametern
 * verweigern fail-closed (der fehlende Eintrag gilt als nicht vorhandenes Recht), statt 500 zu liefern.
 */
final class ZugriffspruefungNullTest extends TestCase
{
    use RefreshDatabase;

    private const GEHEIM = 'GEHEIMER-INHALT';

    private const ROLLE_NORMAL = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => null],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => null],
            ['id' => self::ROLLE_NORMAL, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt, 'deleted_at' => null],
        ]);

        foreach (['a', 'b', 'c'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => strtoupper($recht),
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }

        // Gegenprobe-Routen mit gültigen Parametern
        Route::middleware(['web', 'hasPermission:a'])->get('/_t/ok-permission', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermissionAnd:a,b'])->get('/_t/ok-and', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermissionOr:a,b'])->get('/_t/ok-or', fn () => self::GEHEIM);

        // Routen mit leeren oder fehlenden Parametern
        Route::middleware(['web', 'hasPermission'])->get('/_t/permission-ohne', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermission:'])->get('/_t/permission-leer', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermissionAnd'])->get('/_t/and-ohne', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermissionAnd:'])->get('/_t/and-leer', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermissionAnd:a,'])->get('/_t/and-leeres-zweites', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermissionOr'])->get('/_t/or-ohne', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermissionOr:'])->get('/_t/or-leer', fn () => self::GEHEIM);
        Route::middleware(['web', 'hasPermissionOr:,a'])->get('/_t/or-leeres-erstes', fn () => self::GEHEIM);
    }

    /**
     * Ausdrücke, die ohne ein einziges gültiges Recht auskommen müssen.
     *
     * @return array<string, array{0: string}>
     */
    public static function ausdruckOhneGueltigesRecht(): array
    {
        return [
            'null' => ['null'],
            'Variable mit null' => ['$x'],
            'Array nur mit null' => ['[null]'],
            'leeres Array' => ['[]'],
            'leerer Text' => ["''"],
            'Array mit leerem Text' => ["['']"],
        ];
    }

    /**
     * @param  list<string>  $rechte
     */
    private function normalerNutzerMitRechten(array $rechte): User
    {
        $user = User::factory()->create(['role_id' => self::ROLLE_NORMAL]);
        foreach ($rechte as $recht) {
            DB::table('role_permission')->insert([
                'role_id' => self::ROLLE_NORMAL,
                'permission_id' => DB::table('permissions')->where('permission', $recht)->value('id'),
            ]);
        }

        return $user;
    }

    /**
     * Rendert die Vorlage über eine echte Route: Ein Serverfehler zeigt sich als Status 500.
     *
     * @return array{0: int, 1: string}
     */
    private function rendereUeberRoute(string $vorlage, User $user): array
    {
        $pfad = '/_t/blade/'.md5($vorlage.microtime());
        Route::middleware('web')->get($pfad, fn (): string => trim(Blade::render($vorlage, ['x' => null])));

        $response = $this->actingAs($user)->get($pfad);

        return [$response->getStatusCode(), trim((string) $response->getContent())];
    }

    private function assertRendert(string $erwartet, string $vorlage, User $user): void
    {
        [$status, $inhalt] = $this->rendereUeberRoute($vorlage, $user);

        $this->assertSame(200, $status, "Serverfehler beim Rendern von: {$vorlage}");
        $this->assertSame($erwartet, $inhalt);
    }

    private function assertKeinZugriff(string $url, User $user): void
    {
        $response = $this->actingAs($user)->get($url);

        $this->assertNotSame(200, $response->getStatusCode(), "{$url} darf nicht 200 liefern");
        $this->assertLessThan(500, $response->getStatusCode(), "{$url} darf keinen Serverfehler liefern");
        $this->assertStringNotContainsString(self::GEHEIM, (string) $response->getContent());
    }

    private function assertZugriff(string $url, User $user): void
    {
        $this->actingAs($user)->get($url)->assertOk()->assertSee(self::GEHEIM);
    }

    // ---------------------------------------------------------------- Blade: And

    // AK13
    #[DataProvider('ausdruckOhneGueltigesRecht')]
    public function test_blade_has_permission_and_ohne_gueltiges_recht_verweigert_ohne_serverfehler(string $ausdruck): void
    {
        $user = $this->normalerNutzerMitRechten(['a', 'b']);

        // Gegenprobe: derselbe Nutzer besteht die Direktive mit gültigen Rechten
        $this->assertRendert('JA', "@hasPermissionAnd(['a', 'b']) JA @else NEIN @endhasPermissionAnd", $user);

        $this->assertRendert('NEIN', "@hasPermissionAnd({$ausdruck}) JA @else NEIN @endhasPermissionAnd", $user);
    }

    // AK13
    public function test_blade_has_permission_and_mit_null_element_im_array_verweigert_ohne_serverfehler(): void
    {
        $user = $this->normalerNutzerMitRechten(['a', 'b']);

        // Gegenprobe: ohne null-Element bekommt der Nutzer Zugriff
        $this->assertRendert('JA', "@hasPermissionAnd(['a', 'b']) JA @else NEIN @endhasPermissionAnd", $user);

        // Das null-Element gilt als nicht vorhandenes Recht, auch wenn alle übrigen vorhanden sind
        $this->assertRendert('NEIN', "@hasPermissionAnd(['a', null]) JA @else NEIN @endhasPermissionAnd", $user);
    }

    // AK13
    public function test_blade_has_permission_and_mit_null_element_verweigert_auch_ohne_jedes_recht(): void
    {
        $user = $this->normalerNutzerMitRechten([]);

        $this->assertRendert('NEIN', "@hasPermissionAnd(['a', 'b']) JA @else NEIN @endhasPermissionAnd", $user);
        $this->assertRendert('NEIN', "@hasPermissionAnd(['a', null]) JA @else NEIN @endhasPermissionAnd", $user);
    }

    // ---------------------------------------------------------------- Blade: Or

    // AK13
    #[DataProvider('ausdruckOhneGueltigesRecht')]
    public function test_blade_has_permission_or_ohne_gueltiges_recht_verweigert_ohne_serverfehler(string $ausdruck): void
    {
        $user = $this->normalerNutzerMitRechten(['a', 'b']);

        // Gegenprobe: derselbe Nutzer besteht die Direktive mit gültigen Rechten
        $this->assertRendert('JA', "@hasPermissionOr(['a', 'c']) JA @else NEIN @endhasPermissionOr", $user);

        $this->assertRendert('NEIN', "@hasPermissionOr({$ausdruck}) JA @else NEIN @endhasPermissionOr", $user);
    }

    // AK13
    public function test_blade_has_permission_or_mit_null_element_entscheidet_das_gueltige_recht(): void
    {
        $nutzerMitA = $this->normalerNutzerMitRechten(['a']);

        // Ein vorhandenes gültiges Recht neben dem null-Element genügt
        $this->assertRendert('JA', "@hasPermissionOr(['a', null]) JA @else NEIN @endhasPermissionOr", $nutzerMitA);
        $this->assertRendert('JA', "@hasPermissionOr([null, 'a']) JA @else NEIN @endhasPermissionOr", $nutzerMitA);
    }

    // AK13
    public function test_blade_has_permission_or_mit_null_element_ohne_gueltiges_recht_verweigert(): void
    {
        $nutzerOhneRecht = $this->normalerNutzerMitRechten(['c']);

        // Gegenprobe: ein vorhandenes Recht aus der Liste gewährt Zugriff
        $this->assertRendert('JA', "@hasPermissionOr(['c', null]) JA @else NEIN @endhasPermissionOr", $nutzerOhneRecht);

        $this->assertRendert('NEIN', "@hasPermissionOr(['a', null]) JA @else NEIN @endhasPermissionOr", $nutzerOhneRecht);
    }

    // AK13
    public function test_blade_has_permission_or_mit_variable_null_gewaehrt_auch_privilegierten_ohne_serverfehler(): void
    {
        $dev = User::factory()->create(['role_id' => 1]);

        // Gegenprobe: Dev besteht die Direktive mit gültigem Recht
        $this->assertRendert('JA', "@hasPermissionOr(['a']) JA @else NEIN @endhasPermissionOr", $dev);

        [$status] = $this->rendereUeberRoute('@hasPermissionOr($x) JA @else NEIN @endhasPermissionOr', $dev);
        $this->assertSame(200, $status, 'Auch für Dev/Admin darf null keinen Serverfehler auslösen');
    }

    // AK13
    public function test_blade_has_permission_and_mit_null_element_loest_auch_fuer_privilegierte_keinen_serverfehler_aus(): void
    {
        $admin = User::factory()->create(['role_id' => 2]);

        // Gegenprobe: Admin besteht die Direktive mit gültigen Rechten
        $this->assertRendert('JA', "@hasPermissionAnd(['a', 'b']) JA @else NEIN @endhasPermissionAnd", $admin);

        [$status] = $this->rendereUeberRoute("@hasPermissionAnd(['a', null]) JA @else NEIN @endhasPermissionAnd", $admin);
        $this->assertSame(200, $status, 'Auch für Dev/Admin darf ein null-Element keinen Serverfehler auslösen');
    }

    // ---------------------------------------------------------------- Middleware

    // AK13
    public function test_has_permission_and_ohne_oder_mit_leerem_parameter_verweigert_ohne_serverfehler(): void
    {
        $user = $this->normalerNutzerMitRechten(['a', 'b']);

        // Gegenprobe: derselbe Nutzer kommt über die Route mit gültigen Parametern durch
        $this->assertZugriff('/_t/ok-and', $user);

        $this->assertKeinZugriff('/_t/and-ohne', $user);
        $this->assertKeinZugriff('/_t/and-leer', $user);
    }

    // AK13
    public function test_has_permission_and_mit_leerem_zweiten_parameter_gilt_als_fehlendes_recht_ohne_serverfehler(): void
    {
        $user = $this->normalerNutzerMitRechten(['a']);

        // Gegenprobe: der Aufbau greift – der Nutzer hat `a` und kommt über eine Route mit gültigem Parameter durch
        $this->assertZugriff('/_t/ok-permission', $user);

        $response = $this->actingAs($user)->get('/_t/and-leeres-zweites');
        $this->assertLessThan(500, $response->getStatusCode(), 'Leerer Parameter darf keinen Serverfehler liefern');
    }

    // AK13
    public function test_has_permission_or_ohne_oder_mit_leerem_parameter_verweigert_ohne_serverfehler(): void
    {
        $user = $this->normalerNutzerMitRechten(['a', 'b']);

        // Gegenprobe: derselbe Nutzer kommt über die Route mit gültigen Parametern durch
        $this->assertZugriff('/_t/ok-or', $user);

        $this->assertKeinZugriff('/_t/or-ohne', $user);
        $this->assertKeinZugriff('/_t/or-leer', $user);
    }

    // AK13
    public function test_has_permission_or_mit_leerem_ersten_parameter_entscheidet_das_gueltige_recht(): void
    {
        // Gegenprobe: Nutzer ohne `a` kommt nicht durch (und bekommt keinen Serverfehler)
        $this->assertKeinZugriff('/_t/or-leeres-erstes', $this->normalerNutzerMitRechten(['c']));

        // Mit dem gültigen Recht `a` entscheidet dieses
        $this->assertZugriff('/_t/or-leeres-erstes', $this->normalerNutzerMitRechten(['a']));
    }

    // AK13
    public function test_has_permission_ohne_oder_mit_leerem_parameter_verweigert_ohne_serverfehler(): void
    {
        $user = $this->normalerNutzerMitRechten(['a']);

        // Gegenprobe: derselbe Nutzer kommt über die Route mit gültigem Parameter durch
        $this->assertZugriff('/_t/ok-permission', $user);

        $this->assertKeinZugriff('/_t/permission-leer', $user);
        $this->assertKeinZugriff('/_t/permission-ohne', $user);
    }
}
