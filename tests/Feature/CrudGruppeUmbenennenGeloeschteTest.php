<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Umbenennen einer CRUD-Gruppe zieht auch soft-gelöschte eigene Rechte mit (userauth-rechtenamen, Dashboard #497).
 * Abgedeckt: AK4, AK5, Randfall 3.
 *
 * Aufbau: Gruppe 3 `kunde` (Anzeige „Kunde“) ist eine CRUD-Gruppe mit `kunde_create/_read/_edit/_delete`, davon
 * `kunde_read` soft-gelöscht. Gruppe 4 `fremd` ist eine Einzelrechte-Gruppe (eigenes aktives Recht `fremd_recht`)
 * und Gruppe 5 `lager` eine CRUD-Gruppe mit einem soft-gelöschten Recht `lager_read`.
 */
final class CrudGruppeUmbenennenGeloeschteTest extends TestCase
{
    use RefreshDatabase;

    private const KUNDE = 3;

    private const FREMD = 4;

    private const LAGER = 5;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        View::getFinder()->prependLocation(__DIR__.'/../Fixtures/adminviews');
        config(['laravelkit.theme' => 'tailwind']);

        $jetzt = now();
        DB::table('roles')->insert(['id' => 1, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        foreach ([[self::KUNDE, 'kunde', 'Kunde', 0], [self::FREMD, 'fremd', 'Fremd', 1], [self::LAGER, 'lager', 'Lager', 0]] as [$id, $name, $anzeige, $istGruppe]) {
            DB::table('permissions_groups')->insert([
                'id' => $id, 'group_name' => $name, 'group_display' => $anzeige, 'is_group' => $istGruppe,
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
        foreach (['create', 'read', 'edit', 'delete'] as $art) {
            $this->recht('kunde_'.$art, 'Kunde '.$art, self::KUNDE, $art, $art === 'read');
        }
        $this->recht('fremd_recht', 'Fremdes Recht', self::FREMD, 'single');
        foreach (['create', 'read', 'edit', 'delete'] as $art) {
            $this->recht('lager_'.$art, 'Lager '.$art, self::LAGER, $art, $art === 'read');
        }

        $this->dev = User::factory()->create(['role_id' => 1]);
    }

    private function recht(string $intern, string $anzeige, int $gruppe, string $crud, bool $geloescht = false): int
    {
        return DB::table('permissions')->insertGetId([
            'permission' => $intern, 'permission_display' => $anzeige, 'group_id' => $gruppe, 'crud' => $crud,
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $geloescht ? '2020-01-02 03:04:05' : null,
        ]);
    }

    /** @return array<string, mixed> alle Zeilen beider Tabellen, auch soft-gelöschte */
    private function stand(): array
    {
        return [
            'gruppen' => DB::table('permissions_groups')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all(),
            'rechte' => DB::table('permissions')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all(),
        ];
    }

    /** @return array<string, ?string> Recht-Name => deleted_at der Gruppe, auch soft-gelöschte, nach id */
    private function rechteMitLoeschstand(int $gruppe): array
    {
        return DB::table('permissions')->where('group_id', $gruppe)->orderBy('id')->pluck('deleted_at', 'permission')->all();
    }

    /** @return list<string> */
    private function alleNamen(int $gruppe): array
    {
        return DB::table('permissions')->where('group_id', $gruppe)->orderBy('id')->pluck('permission')->all();
    }

    /** @return TestResponse<Response> */
    private function umbenennen(string $name, string $anzeige = 'Neu'): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.edit', self::KUNDE))
            ->post(route('permission.update', self::KUNDE), ['group_display' => $anzeige, 'group_name' => $name]);
    }

    /** @return array<string, array{0: string}> */
    public static function crudArten(): array
    {
        return ['create' => ['create'], 'read' => ['read'], 'edit' => ['edit'], 'delete' => ['delete']];
    }

    /** Löscht genau das eigene Recht der Art weich und stellt kunde_read wieder her (read ist im Aufbau gelöscht). */
    private function geloeschtesRechtWaehlen(string $art): void
    {
        DB::table('permissions')->where('group_id', self::KUNDE)->update(['deleted_at' => null]);
        $this->assertSame(1, DB::table('permissions')->where('permission', 'kunde_'.$art)->update(['deleted_at' => '2020-01-02 03:04:05']));
    }

    // ---------------------------------------------------------------- AK4

    // AK4
    public function test_ak4_umbenennen_setzt_den_neuen_praefix_auch_am_soft_geloeschten_recht(): void
    {
        // Gegenprobe: im Aufbau ist kunde_read weich gelöscht, die anderen sind aktiv
        $this->assertNotNull(DB::table('permissions')->where('permission', 'kunde_read')->value('deleted_at'));

        $this->umbenennen('Neu')->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $this->assertSame(['neu_create', 'neu_read', 'neu_edit', 'neu_delete'], $this->alleNamen(self::KUNDE));
        $this->assertSame(0, DB::table('permissions')->where('permission', 'like', 'kunde\_%')->count());
    }

    // AK4
    #[DataProvider('crudArten')]
    public function test_ak4_jedes_der_vier_rechte_wird_umbenannt_auch_wenn_es_das_geloeschte_ist(string $art): void
    {
        $this->geloeschtesRechtWaehlen($art);

        $this->umbenennen('Neu')->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $this->assertSame(['neu_create', 'neu_read', 'neu_edit', 'neu_delete'], $this->alleNamen(self::KUNDE));
    }

    // AK4
    public function test_ak4_das_umbenannte_recht_bleibt_soft_geloescht_und_die_uebrigen_aktiv(): void
    {
        $this->umbenennen('Neu')->assertSessionHasNoErrors();

        $stand = $this->rechteMitLoeschstand(self::KUNDE);
        $this->assertSame(['neu_create', 'neu_read', 'neu_edit', 'neu_delete'], array_keys($stand));
        $this->assertNotNull($stand['neu_read'], 'neu_read bleibt soft-gelöscht');
        $this->assertNull($stand['neu_create']);
        $this->assertNull($stand['neu_edit']);
        $this->assertNull($stand['neu_delete']);
    }

    // AK4
    public function test_ak4_aendert_nur_eigene_rechte_der_gruppe(): void
    {
        $fremdVorher = [DB::table('permissions')->where('group_id', self::FREMD)->get()->all(), DB::table('permissions')->where('group_id', self::LAGER)->get()->all()];

        $this->umbenennen('Neu')->assertSessionHasNoErrors();

        // Gegenprobe: die eigene Gruppe wurde umbenannt
        $this->assertSame('neu_create', DB::table('permissions')->where('group_id', self::KUNDE)->orderBy('id')->value('permission'));
        $this->assertEquals($fremdVorher, [DB::table('permissions')->where('group_id', self::FREMD)->get()->all(), DB::table('permissions')->where('group_id', self::LAGER)->get()->all()]);
        $this->assertNotNull(DB::table('permissions')->where('permission', 'lager_read')->value('deleted_at'));
    }

    // AK4
    public function test_ak4_nach_dem_umbenennen_laesst_sich_eine_neue_crud_gruppe_kunde_anlegen(): void
    {
        $this->umbenennen('Neu')->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $antwort = $this->actingAs($this->dev)->from(route('permission.create'))
            ->post(route('permission.store'), ['group_display' => 'Kunde', 'group_name' => 'Kunde']);

        $antwort->assertSessionHasNoErrors()->assertRedirect(route('permission'));
        $neu = (int) DB::table('permissions_groups')->where('group_name', 'kunde')->value('id');
        $this->assertGreaterThan(0, $neu);
        $this->assertNotSame(self::KUNDE, $neu);
        $this->assertSame(['kunde_create', 'kunde_read', 'kunde_edit', 'kunde_delete'], $this->alleNamen($neu));
    }

    // AK4
    public function test_ak4_scheitert_ein_recht_beim_umbenennen_bleibt_alles_unveraendert_im_selben_transaktionsblock(): void
    {
        // Das soft-gelöschte Recht bekommt als zweites den neuen Namen; das Scheitern dort muss Gruppe und
        // das schon umbenannte Recht zurückrollen. Der Auslöser sitzt in der Datenbank, unabhängig davon, wie der Code schreibt.
        DB::unprepared("CREATE TRIGGER test_scheitert BEFORE UPDATE ON permissions WHEN NEW.permission = 'neu_read' BEGIN SELECT RAISE(ABORT, 'test'); END");
        $vorher = $this->stand();

        $geworfen = null;
        try {
            $this->withoutExceptionHandling();
            $this->umbenennen('Neu');
        } catch (Throwable $e) {
            $geworfen = $e;
        }

        $this->assertNotNull($geworfen, 'das Umbenennen des soft-gelöschten Rechts wurde versucht und ist gescheitert');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: ohne Auslöser läuft dieselbe Anfrage durch
        DB::unprepared('DROP TRIGGER test_scheitert');
        $this->umbenennen('Neu')->assertSessionHasNoErrors();
        $this->assertSame(['neu_create', 'neu_read', 'neu_edit', 'neu_delete'], $this->alleNamen(self::KUNDE));
    }

    // ---------------------------------------------------------------- AK5

    /** @return array<string, array{0: string, 1: bool}> */
    public static function fremdeRechte(): array
    {
        return [
            'create, aktiv' => ['create', false],
            'read, aktiv' => ['read', false],
            'edit, aktiv' => ['edit', false],
            'delete, aktiv' => ['delete', false],
            'create, soft-gelöscht' => ['create', true],
            'read, soft-gelöscht' => ['read', true],
            'edit, soft-gelöscht' => ['edit', true],
            'delete, soft-gelöscht' => ['delete', true],
        ];
    }

    // AK5
    #[DataProvider('fremdeRechte')]
    public function test_ak5_fremdes_recht_mit_neuem_namen_ergibt_fehler_am_feld_group_name(string $art, bool $geloescht): void
    {
        $this->recht('neu_'.$art, 'Fremd '.$art, self::FREMD, 'single', $geloescht);

        $antwort = $this->umbenennen('Neu');

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::KUNDE));
        $antwort->assertSessionHasErrors(['group_name' => __('userauth::permission.NameVergeben')]);
    }

    // AK5
    #[DataProvider('fremdeRechte')]
    public function test_ak5_bei_kollision_bleibt_alles_unveraendert(string $art, bool $geloescht): void
    {
        $this->recht('neu_'.$art, 'Fremd '.$art, self::FREMD, 'single', $geloescht);
        $vorher = $this->stand();

        $this->umbenennen('Neu')->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: dieselbe Anfrage mit freiem Namen benennt um
        $this->umbenennen('Frei')->assertSessionHasNoErrors()->assertRedirect(route('permission'));
        $this->assertSame(['frei_create', 'frei_read', 'frei_edit', 'frei_delete'], $this->alleNamen(self::KUNDE));
    }

    // AK5
    public function test_ak5_der_fehler_steht_in_der_bearbeiten_seite(): void
    {
        $this->recht('neu_read', 'Fremd read', self::FREMD, 'single', true);

        $this->umbenennen('Neu')->assertSessionHasErrors('group_name');
        $meldung = session('errors')->first('group_name');
        $this->assertNotSame('', $meldung);

        $this->actingAs($this->dev)->from(route('permission.edit', self::KUNDE))->followingRedirects()
            ->post(route('permission.update', self::KUNDE), ['group_display' => 'Neu', 'group_name' => 'Neu'])
            ->assertOk()->assertSee(e($meldung), false);
    }

    // ---------------------------------------------------------------- Randfall 3

    // Randfall 3
    public function test_randfall3_ohne_umbenennen_aendert_sich_an_den_rechten_nichts(): void
    {
        $vorher = DB::table('permissions')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all();

        // gleicher Name, nur die Schreibweise der Eingabe unterscheidet sich: formatiert ergibt das `kunde`
        $this->umbenennen('KUNDE', 'Kunde')->assertSessionHasNoErrors()->assertRedirect(route('permission'));

        $this->assertSame('kunde', DB::table('permissions_groups')->where('id', self::KUNDE)->value('group_name'));
        $nachher = DB::table('permissions')->orderBy('id')->get()->map(fn ($z): array => (array) $z)->all();
        $this->assertSame(array_column($vorher, 'permission'), array_column($nachher, 'permission'));
        $this->assertSame(array_column($vorher, 'deleted_at'), array_column($nachher, 'deleted_at'));
    }

    // Randfall 3
    public function test_randfall3_das_soft_geloeschte_recht_bleibt_bei_gleichem_namen_unveraendert(): void
    {
        $vorher = (array) DB::table('permissions')->where('permission', 'kunde_read')->first();
        $this->assertNotNull($vorher['deleted_at']);

        $this->umbenennen('kunde', 'Kunde')->assertSessionHasNoErrors();

        $this->assertSame($vorher, (array) DB::table('permissions')->where('permission', 'kunde_read')->first());

        // Gegenprobe: erst das Umbenennen ändert das Recht
        $this->umbenennen('Neu')->assertSessionHasNoErrors();
        $this->assertNull(DB::table('permissions')->where('permission', 'kunde_read')->first());
    }
}
