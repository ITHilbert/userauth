<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Entities\Permission;
use ITHilbert\UserAuth\Entities\PermissionGroup;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use ITHilbert\UserAuth\Traits\Format;
use Pdo\Sqlite;
use PHPUnit\Framework\Attributes\DataProvider;
use Transliterator;

/**
 * Gruppenname formatiert und eindeutig, abgeleitete CRUD-Rechtenamen frei (userauth-rechtegruppe-namen, Dashboard #390, #382).
 *
 * Aufbau: Gruppe 3 `alpha` und Gruppe 4 `beta` sind CRUD-Gruppen (je vier Rechte `<name>_create/_read/_edit/_delete`),
 * Gruppe 5 `einzel` ist eine Einzelrechte-Gruppe (Recht `einzelrecht`) und besitzt die fremden Rechte `kunde_read`,
 * `lager_edit` (aktiv) sowie `depot_create`, `silo_delete` (soft-gelöscht). `permission.store` legt „Neue Gruppe“ an.
 * DatabaseMigrations (statt RefreshDatabase), weil die Kollations-Tests Tabellen neu anlegen.
 */
final class RechtegruppeNamenTest extends TestCase
{
    use DatabaseMigrations;

    private const ALPHA = 3;

    private const BETA = 4;

    private const EINZEL = 5;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        View::getFinder()->prependLocation(__DIR__.'/../Fixtures/adminviews');
        config(['laravelkit.theme' => 'tailwind']);

        $jetzt = now();
        foreach ([[1, 'dev', 'Dev'], [2, 'admin', 'Admin']] as [$id, $name, $anzeige]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => $anzeige, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }

        foreach ([[self::ALPHA, 'alpha', 'Alpha', 0], [self::BETA, 'beta', 'Beta', 0], [self::EINZEL, 'einzel', 'Einzel', 1]] as [$id, $name, $anzeige, $istGruppe]) {
            DB::table('permissions_groups')->insert([
                'id' => $id, 'group_name' => $name, 'group_display' => $anzeige, 'is_group' => $istGruppe,
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }

        foreach ([self::ALPHA => 'alpha', self::BETA => 'beta'] as $gruppe => $name) {
            foreach (['create', 'read', 'edit', 'delete'] as $crud) {
                $this->recht($name.'_'.$crud, ucfirst($name).' '.$crud, $gruppe, null, $crud);
            }
        }
        $this->recht('einzelrecht', 'Einzelrecht', self::EINZEL);
        $this->recht('kunde_read', 'Kunde lesen', self::EINZEL);
        $this->recht('lager_edit', 'Lager ändern', self::EINZEL);
        $this->recht('depot_create', 'Depot anlegen', self::EINZEL, '2020-01-02 03:04:05');
        $this->recht('silo_delete', 'Silo löschen', self::EINZEL, '2020-01-02 03:04:05');

        $this->dev = User::factory()->create(['role_id' => 1, 'name' => 'Dev Nutzer', 'email' => 'dev@example.test']);
    }

    private function recht(string $intern, string $anzeige, int $gruppe, ?string $geloescht = null, string $crud = 'single'): int
    {
        return DB::table('permissions')->insertGetId([
            'permission' => $intern, 'permission_display' => $anzeige, 'group_id' => $gruppe, 'crud' => $crud,
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $geloescht,
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

    /** @return list<string> */
    private function rechteNamen(int $gruppe): array
    {
        return DB::table('permissions')->where('group_id', $gruppe)->whereNull('deleted_at')->orderBy('id')->pluck('permission')->all();
    }

    private function formatiert(string $anzeige): string
    {
        return (new class
        {
            use Format;
        })->formatDisplayToIntern($anzeige);
    }

    /**
     * @param  array<string, mixed>  $felder
     * @return array<string, mixed>
     */
    private function storeFelder(array $felder, bool $einzelrechte = false): array
    {
        $basis = ['group_display' => 'Neue Gruppe', 'group_name' => 'neuegruppe'];
        if ($einzelrechte) {
            $basis = array_merge($basis, ['ckPermissionGroup' => 'on', 'permission_display_new_1' => 'Neues Einzelrecht']);
        }

        return array_merge($basis, $felder);
    }

    /**
     * @param  array<string, mixed>  $felder
     */
    private function store(array $felder, bool $einzelrechte = false): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.create'))->post(route('permission.store'), $this->storeFelder($felder, $einzelrechte));
    }

    /**
     * @param  array<string, mixed>  $felder
     */
    private function storeFolgen(array $felder, bool $einzelrechte = false): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.create'))->followingRedirects()->post(route('permission.store'), $this->storeFelder($felder, $einzelrechte));
    }

    /**
     * @param  array<string, mixed>  $felder
     * @return array<string, mixed>
     */
    private function updateFelder(int $gruppe, array $felder): array
    {
        $basis = match ($gruppe) {
            self::ALPHA => ['group_display' => 'Alpha', 'group_name' => 'alpha'],
            self::BETA => ['group_display' => 'Beta', 'group_name' => 'beta'],
            default => ['group_display' => 'Einzel', 'group_name' => 'einzel'],
        };

        return array_merge($basis, $felder);
    }

    /**
     * @param  array<string, mixed>  $felder
     */
    private function update(int $gruppe, array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.edit', $gruppe))->post(route('permission.update', $gruppe), $this->updateFelder($gruppe, $felder));
    }

    /**
     * @param  array<string, mixed>  $felder
     */
    private function updateFolgen(int $gruppe, array $felder): TestResponse
    {
        return $this->actingAs($this->dev)->from(route('permission.edit', $gruppe))->followingRedirects()->post(route('permission.update', $gruppe), $this->updateFelder($gruppe, $felder));
    }

    /** @return array<string, string> Feldname => Wert aller Eingabefelder der Seite */
    private function eingaben(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        $werte = [];
        foreach ((new DOMXPath($dom))->query('//input[@name and not(@disabled)]') ?: [] as $feld) {
            $werte[$feld->getAttribute('name')] = $feld->getAttribute('value');
        }

        return $werte;
    }

    private function meldung(): string
    {
        return __('userauth::permission.NameVergeben');
    }

    // ---------------------------------------------------------------- AK1

    /** @return array<string, array{0: string}> */
    public static function crudNamenVergeben(): array
    {
        return [
            'Recht kunde_read aktiv' => ['Kunde'],
            'Recht kunde_read aktiv, Großschreibung' => ['KUNDE'],
            'Recht kunde_read aktiv, mit Leerzeichen' => ['Ku nde'],
            'Recht depot_create soft-gelöscht' => ['Depot'],
        ];
    }

    // AK1
    #[DataProvider('crudNamenVergeben')]
    public function test_ak1_store_crud_mit_vergebenem_rechtenamen_ergibt_validierungsfehler_am_feld(string $eingabe): void
    {
        $antwort = $this->store(['group_name' => $eingabe]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.create'));
        $antwort->assertSessionHasErrors(['group_name' => $this->meldung()]);

        // Gegenprobe: dieselbe Anfrage mit freiem Namen legt an
        $this->store(['group_name' => 'Frei'])->assertRedirect(route('permission'));
        $this->assertSame(['frei_create', 'frei_read', 'frei_edit', 'frei_delete'], $this->rechteNamen((int) DB::table('permissions_groups')->where('group_name', 'frei')->value('id')));
    }

    // AK1
    #[DataProvider('crudNamenVergeben')]
    public function test_ak1_store_crud_mit_vergebenem_rechtenamen_legt_weder_gruppe_noch_recht_an(string $eingabe): void
    {
        $vorher = $this->stand();

        $this->store(['group_name' => $eingabe])->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: der Aufbau legt mit freiem Namen Gruppe und Rechte an
        $this->store(['group_name' => 'Frei'])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('permissions_groups')->where('group_name', 'frei')->count());
        $this->assertSame(4, DB::table('permissions')->whereIn('permission', ['frei_create', 'frei_read', 'frei_edit', 'frei_delete'])->count());
    }

    // AK1
    #[DataProvider('crudNamenVergeben')]
    public function test_ak1_store_crud_mit_vergebenem_rechtenamen_behaelt_die_eingaben(string $eingabe): void
    {
        $this->store(['group_name' => $eingabe, 'group_display' => 'Meine Anzeige'])->assertSessionHasErrors('group_name');

        $this->assertSame($eingabe, session()->getOldInput('group_name'));
        $this->assertSame('Meine Anzeige', session()->getOldInput('group_display'));
    }

    // AK1
    public function test_ak1_store_zeigt_fehler_und_eingaben_in_der_seite(): void
    {
        $felder = ['group_name' => 'Kunde', 'group_display' => 'Meine Anzeige'];

        $this->store($felder)->assertSessionHasErrors('group_name');
        $meldung = session('errors')->first('group_name');
        $this->assertSame($this->meldung(), $meldung);

        $seite = $this->storeFolgen($felder);
        $seite->assertOk()->assertSee(e($meldung), false);
        $eingaben = $this->eingaben($seite->getContent());
        $this->assertSame('Kunde', $eingaben['group_name'] ?? null);
        $this->assertSame('Meine Anzeige', $eingaben['group_display'] ?? null);
    }

    // AK1
    public function test_ak1_vergebener_rechtename_soft_geloescht_sperrt_auch_das_anlegen_ueber_die_aktive_variante(): void
    {
        // Gegenprobe: depot_create ist soft-gelöscht und trotzdem belegt (Unique-Index der DB)
        $this->assertNotNull(DB::table('permissions')->where('permission', 'depot_create')->value('deleted_at'));
        $this->assertNull(Permission::where('permission', 'depot_create')->first());

        $this->store(['group_name' => 'Depot'])->assertSessionHasErrors('group_name');
        $this->assertSame(0, DB::table('permissions_groups')->where('group_name', 'depot')->count());
    }

    // ---------------------------------------------------------------- AK2

    /** @return array<string, array{0: string}> */
    public static function gruppenNamenVergeben(): array
    {
        return [
            'Großschreibung' => ['Beta'],
            'ganz groß' => ['BETA'],
            'mit Leerzeichen' => ['Be ta'],
        ];
    }

    // AK2
    #[DataProvider('gruppenNamenVergeben')]
    public function test_ak2_store_crud_mit_formatiert_gleichem_gruppennamen_ergibt_fehler_am_feld(string $eingabe): void
    {
        $vorher = $this->stand();

        $antwort = $this->store(['group_name' => $eingabe]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.create'));
        $antwort->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: freier Name wird angelegt
        $this->store(['group_name' => 'Gamma'])->assertRedirect(route('permission'));
        $this->assertSame(1, DB::table('permissions_groups')->where('group_name', 'gamma')->count());
    }

    // AK2
    #[DataProvider('gruppenNamenVergeben')]
    public function test_ak2_store_einzelrechte_mit_formatiert_gleichem_gruppennamen_ergibt_fehler_am_feld(string $eingabe): void
    {
        $vorher = $this->stand();

        $antwort = $this->store(['group_name' => $eingabe], true);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.create'));
        $antwort->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: freier Name legt Gruppe und Einzelrecht an
        $this->store(['group_name' => 'Gamma'], true)->assertRedirect(route('permission'));
        $this->assertSame(1, DB::table('permissions_groups')->where('group_name', 'gamma')->where('is_group', 1)->count());
        $this->assertSame(1, DB::table('permissions')->where('permission', 'neueseinzelrecht')->count());
    }

    // AK2
    public function test_ak2_store_zeigt_den_gruppennamen_fehler_in_der_seite(): void
    {
        $this->store(['group_name' => 'Beta'])->assertSessionHasErrors('group_name');
        $meldung = session('errors')->first('group_name');
        $this->assertNotSame('', $meldung);

        $this->storeFolgen(['group_name' => 'Beta'])->assertOk()->assertSee(e($meldung), false);
    }

    // ---------------------------------------------------------------- AK3

    /** @return array<string, array{0: string}> */
    public static function neueNamen(): array
    {
        return [
            'Leerzeichen und Großbuchstaben' => ['Neuer Kunde'],
            'Umlaute und Sonderzeichen' => ['Müller:Süd'],
        ];
    }

    // AK3
    #[DataProvider('neueNamen')]
    public function test_ak3_update_crud_speichert_den_formatierten_gruppennamen_und_die_rechtenamen(string $eingabe): void
    {
        $erwartet = $this->formatiert($eingabe);
        $this->assertNotSame($eingabe, $erwartet);

        $this->update(self::ALPHA, ['group_name' => $eingabe])->assertRedirect(route('permission'));

        $this->assertSame($erwartet, DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
        $this->assertSame(
            [$erwartet.'_create', $erwartet.'_read', $erwartet.'_edit', $erwartet.'_delete'],
            $this->rechteNamen(self::ALPHA)
        );
    }

    // AK3
    public function test_ak3_update_einzelrechte_gruppe_speichert_den_formatierten_gruppennamen(): void
    {
        $this->update(self::EINZEL, ['group_name' => 'Neuer Kunde'])->assertRedirect(route('permission'));

        $this->assertSame('neuerkunde', DB::table('permissions_groups')->where('id', self::EINZEL)->value('group_name'));
        // die Einzelrechte bleiben unverändert
        $this->assertSame(['einzelrecht', 'kunde_read', 'lager_edit'], $this->rechteNamen(self::EINZEL));
    }

    // ---------------------------------------------------------------- AK4

    // AK4
    #[DataProvider('gruppenNamenVergeben')]
    public function test_ak4_update_mit_formatiert_gleichem_namen_wie_andere_gruppe_ergibt_fehler_am_feld(string $eingabe): void
    {
        $antwort = $this->update(self::ALPHA, ['group_name' => $eingabe]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::ALPHA));
        $antwort->assertSessionHasErrors('group_name');
    }

    // AK4
    #[DataProvider('gruppenNamenVergeben')]
    public function test_ak4_update_mit_vergebenem_namen_aendert_nichts(string $eingabe): void
    {
        $vorher = $this->stand();

        $this->update(self::ALPHA, ['group_name' => $eingabe, 'group_display' => 'Alpha Neu'])->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: dieselbe Anfrage mit freiem Namen ändert
        $this->update(self::ALPHA, ['group_name' => 'Gamma', 'group_display' => 'Alpha Neu'])->assertRedirect(route('permission'));
        $this->assertSame('Alpha Neu', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_display'));
        $this->assertSame('gamma', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
    }

    // AK4
    public function test_ak4_update_einzelrechte_gruppe_mit_namen_einer_anderen_gruppe_ergibt_fehler_am_feld(): void
    {
        $vorher = $this->stand();

        $this->update(self::EINZEL, ['group_name' => 'Beta'])->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: freier Name wird gespeichert
        $this->update(self::EINZEL, ['group_name' => 'Gamma'])->assertRedirect(route('permission'));
        $this->assertSame('gamma', DB::table('permissions_groups')->where('id', self::EINZEL)->value('group_name'));
    }

    // AK4
    public function test_ak4_update_zeigt_den_fehler_in_der_seite(): void
    {
        $this->update(self::ALPHA, ['group_name' => 'Beta'])->assertSessionHasErrors('group_name');
        $meldung = session('errors')->first('group_name');
        $this->assertNotSame('', $meldung);

        $seite = $this->updateFolgen(self::ALPHA, ['group_name' => 'Beta']);
        $seite->assertOk()->assertSee(e($meldung), false);
        $this->assertSame('Beta', $this->eingaben($seite->getContent())['group_name'] ?? null);
    }

    /** @return array<string, array{0: string}> */
    public static function eigeneNamen(): array
    {
        return [
            'unverändert' => ['alpha'],
            'andere Schreibweise desselben Namens' => ['Alpha'],
        ];
    }

    // AK4
    #[DataProvider('eigeneNamen')]
    public function test_ak4_update_mit_dem_eigenen_namen_ist_erlaubt(string $eingabe): void
    {
        $this->update(self::ALPHA, ['group_name' => $eingabe, 'group_display' => 'Alpha Neu'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('permission'));

        $this->assertSame('Alpha Neu', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_display'));
        $this->assertSame('alpha', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
        $this->assertSame(['alpha_create', 'alpha_read', 'alpha_edit', 'alpha_delete'], $this->rechteNamen(self::ALPHA));
    }

    // AK4
    public function test_ak4_update_einzelrechte_gruppe_mit_dem_eigenen_namen_ist_erlaubt(): void
    {
        $this->update(self::EINZEL, ['group_name' => 'Einzel', 'group_display' => 'Einzel Neu'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('permission'));

        $this->assertSame('Einzel Neu', DB::table('permissions_groups')->where('id', self::EINZEL)->value('group_display'));
        $this->assertSame('einzel', DB::table('permissions_groups')->where('id', self::EINZEL)->value('group_name'));
    }

    // AK4 (Nicht-Ziel: group_display wird beim Bearbeiten nicht zusätzlich eindeutig gemacht)
    public function test_ak4_nicht_ziel_update_darf_die_anzeige_einer_anderen_gruppe_behalten(): void
    {
        $this->update(self::ALPHA, ['group_display' => 'Beta'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('permission'));

        $this->assertSame('Beta', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_display'));
    }

    // ---------------------------------------------------------------- AK5

    /** @return array<string, array{0: string}> */
    public static function fremdeRechte(): array
    {
        return [
            'Recht lager_edit einer anderen Gruppe' => ['Lager'],
            'Recht lager_edit, Großschreibung' => ['LAGER'],
            'Recht silo_delete soft-gelöscht' => ['Silo'],
            'Recht einer anderen CRUD-Gruppe (beta_read)' => ['Beta '],
        ];
    }

    // AK5
    #[DataProvider('fremdeRechte')]
    public function test_ak5_update_crud_mit_vergebenem_rechtenamen_ergibt_fehler_am_feld(string $eingabe): void
    {
        $antwort = $this->update(self::ALPHA, ['group_name' => $eingabe]);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::ALPHA));
        $antwort->assertSessionHasErrors('group_name');
    }

    // AK5
    public function test_ak5_update_zeigt_den_rechtenamen_fehler_in_der_seite(): void
    {
        $this->update(self::ALPHA, ['group_name' => 'Lager'])->assertSessionHasErrors('group_name');
        $meldung = session('errors')->first('group_name');
        $this->assertSame($this->meldung(), $meldung);

        $this->updateFolgen(self::ALPHA, ['group_name' => 'Lager'])->assertOk()->assertSee(e($meldung), false);
    }

    // AK5
    #[DataProvider('fremdeRechte')]
    public function test_ak5_update_crud_mit_vergebenem_rechtenamen_benennt_weder_gruppe_noch_rechte_um(string $eingabe): void
    {
        $vorher = $this->stand();

        $this->update(self::ALPHA, ['group_name' => $eingabe, 'group_display' => 'Alpha Neu'])->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        // Gegenprobe: dieselbe Anfrage mit freiem Namen benennt Gruppe und Rechte um
        $this->update(self::ALPHA, ['group_name' => 'Frei', 'group_display' => 'Alpha Neu'])->assertRedirect(route('permission'));
        $this->assertSame('frei', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
        $this->assertSame(['frei_create', 'frei_read', 'frei_edit', 'frei_delete'], $this->rechteNamen(self::ALPHA));
    }

    // AK5
    public function test_ak5_die_eigenen_vier_rechte_zaehlen_nicht_als_kollision(): void
    {
        // Gegenprobe: ein fremdes Recht mit Namen alpha_* gäbe es nicht; die vier eigenen Rechte tragen ihn
        $this->assertSame(4, DB::table('permissions')->where('group_id', self::ALPHA)->whereIn('permission', ['alpha_create', 'alpha_read', 'alpha_edit', 'alpha_delete'])->count());

        $this->update(self::ALPHA, ['group_name' => 'ALPHA', 'group_display' => 'Alpha Neu'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('permission'));

        $this->assertSame('alpha', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
        $this->assertSame(['alpha_create', 'alpha_read', 'alpha_edit', 'alpha_delete'], $this->rechteNamen(self::ALPHA));
        $this->assertSame('Alpha Neu', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_display'));
    }

    // ---------------------------------------------------------------- AK6

    // AK6
    public function test_ak6_store_crud_legt_gruppe_und_vier_rechte_an_mit_meldung_und_weiterleitung(): void
    {
        $antwort = $this->store(['group_name' => 'Neuer Kunde', 'group_display' => 'Neue Gruppe']);

        $antwort->assertRedirect(route('permission'));
        $antwort->assertSessionHas('message', __('userauth::permission.MsgAddSuccess'));
        $antwort->assertSessionHas('alert-type', 'success');

        $gruppe = DB::table('permissions_groups')->where('group_display', 'Neue Gruppe')->first();
        $this->assertNotNull($gruppe);
        $this->assertSame('neuerkunde', $gruppe->group_name);
        $this->assertSame(0, (int) $gruppe->is_group);

        $rechte = DB::table('permissions')->where('group_id', $gruppe->id)->orderBy('id')->get();
        $this->assertSame(['create', 'read', 'edit', 'delete'], $rechte->pluck('crud')->all());
        $this->assertSame(['neuerkunde_create', 'neuerkunde_read', 'neuerkunde_edit', 'neuerkunde_delete'], $rechte->pluck('permission')->all());
        $this->assertSame('Neue Gruppe '.__('userauth::permission.read'), $rechte[1]->permission_display);
    }

    // AK6
    public function test_ak6_store_einzelrechte_legt_gruppe_und_recht_an_mit_meldung_und_weiterleitung(): void
    {
        $antwort = $this->store(['group_name' => 'Gamma'], true);

        $antwort->assertRedirect(route('permission'));
        $antwort->assertSessionHas('message', __('userauth::permission.MsgAddSuccess'));
        $antwort->assertSessionHas('alert-type', 'success');

        $gruppe = DB::table('permissions_groups')->where('group_name', 'gamma')->first();
        $this->assertNotNull($gruppe);
        $this->assertSame(1, (int) $gruppe->is_group);
        $this->assertSame(['neueseinzelrecht'], $this->rechteNamen((int) $gruppe->id));
    }

    // AK6
    public function test_ak6_update_crud_benennt_gruppe_und_rechte_um_mit_meldung_und_weiterleitung(): void
    {
        $antwort = $this->update(self::ALPHA, ['group_name' => 'Gamma', 'group_display' => 'Gamma Anzeige']);

        $antwort->assertRedirect(route('permission'));
        $antwort->assertSessionHas('message', __('userauth::permission.MsgEditSuccess'));
        $antwort->assertSessionHas('alert-type', 'success');

        $this->assertSame('gamma', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
        $this->assertSame('Gamma Anzeige', DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_display'));
        $rechte = DB::table('permissions')->where('group_id', self::ALPHA)->orderBy('id')->get();
        $this->assertSame(['gamma_create', 'gamma_read', 'gamma_edit', 'gamma_delete'], $rechte->pluck('permission')->all());
        $this->assertSame(['create', 'read', 'edit', 'delete'], $rechte->pluck('crud')->all());
        $this->assertSame('Gamma Anzeige '.__('userauth::permission.edit'), $rechte[2]->permission_display);
    }

    // AK6
    public function test_ak6_update_einzelrechte_gruppe_aendert_wie_bisher_mit_meldung_und_weiterleitung(): void
    {
        $id = (int) DB::table('permissions')->where('permission', 'einzelrecht')->value('id');

        $antwort = $this->update(self::EINZEL, [
            'group_name' => 'Gamma',
            'permission_display_'.$id => 'Einzelrecht geändert',
            'permission_display_new_1' => 'Zusatz',
        ]);

        $antwort->assertRedirect(route('permission'));
        $antwort->assertSessionHas('message', __('userauth::permission.MsgEditSuccess'));
        $antwort->assertSessionHas('alert-type', 'success');
        $this->assertSame('gamma', DB::table('permissions_groups')->where('id', self::EINZEL)->value('group_name'));
        $this->assertSame('Einzelrecht geändert', DB::table('permissions')->where('id', $id)->value('permission_display'));
        $this->assertSame(1, DB::table('permissions')->where('permission', 'zusatz')->count());
    }

    // ---------------------------------------------------------------- Randfall 2 (Rennen)

    // Randfall 2
    public function test_randfall2_rennen_beim_anlegen_der_rechte_ergibt_fehler_am_feld_ohne_500(): void
    {
        $vorher = $this->stand();
        $ausgeloest = false;
        $this->app['events']->listen('eloquent.creating: '.Permission::class, function () use (&$ausgeloest): void {
            if ($ausgeloest) {
                return;
            }
            $ausgeloest = true;
            // eine parallele Anfrage legt nach der Prüfung ein Recht mit dem Namen an
            $this->recht('wettlauf_read', 'Wettlauf', self::EINZEL);
        });

        $antwort = $this->store(['group_name' => 'Wettlauf']);

        $this->assertTrue($ausgeloest, 'Der Aufbau greift: das Recht der parallelen Anfrage wurde angelegt.');
        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.create'));
        $antwort->assertSessionHasErrors(['group_name' => $this->meldung()]);
        $this->assertSame($vorher, $this->stand());
    }

    // Randfall 2
    public function test_randfall2_rennen_beim_anlegen_der_gruppe_ergibt_fehler_am_feld_ohne_500(): void
    {
        $vorher = $this->stand();
        $ausgeloest = false;
        $this->app['events']->listen('eloquent.creating: '.PermissionGroup::class, function () use (&$ausgeloest): void {
            if ($ausgeloest) {
                return;
            }
            $ausgeloest = true;
            DB::table('permissions_groups')->insert(['group_name' => 'wettlauf', 'group_display' => 'Wettlauf parallel', 'is_group' => 0, 'created_at' => now(), 'updated_at' => now()]);
        });

        $antwort = $this->store(['group_name' => 'Wettlauf']);

        $this->assertTrue($ausgeloest, 'Der Aufbau greift: die Gruppe der parallelen Anfrage wurde angelegt.');
        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors(['group_name' => $this->meldung()]);
        $this->assertSame($vorher, $this->stand());
    }

    // Randfall 2
    public function test_randfall2_rennen_beim_umbenennen_der_rechte_ergibt_fehler_am_feld_ohne_500(): void
    {
        $vorher = $this->stand();
        $ausgeloest = false;
        $this->app['events']->listen('eloquent.updating: '.Permission::class, function () use (&$ausgeloest): void {
            if ($ausgeloest) {
                return;
            }
            $ausgeloest = true;
            $this->recht('wettlauf_edit', 'Wettlauf', self::EINZEL);
        });

        $antwort = $this->update(self::ALPHA, ['group_name' => 'Wettlauf']);

        $this->assertTrue($ausgeloest, 'Der Aufbau greift: das Recht der parallelen Anfrage wurde angelegt.');
        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::ALPHA));
        $antwort->assertSessionHasErrors(['group_name' => $this->meldung()]);
        $this->assertSame($vorher, $this->stand());
    }

    // Randfall 2
    public function test_randfall2_rennen_beim_umbenennen_der_gruppe_ergibt_fehler_am_feld_ohne_500(): void
    {
        $vorher = $this->stand();
        $ausgeloest = false;
        $this->app['events']->listen('eloquent.updating: '.PermissionGroup::class, function () use (&$ausgeloest): void {
            if ($ausgeloest) {
                return;
            }
            $ausgeloest = true;
            DB::table('permissions_groups')->insert(['group_name' => 'wettlauf', 'group_display' => 'Wettlauf parallel', 'is_group' => 0, 'created_at' => now(), 'updated_at' => now()]);
        });

        $antwort = $this->update(self::ALPHA, ['group_name' => 'Wettlauf']);

        $this->assertTrue($ausgeloest, 'Der Aufbau greift: die Gruppe der parallelen Anfrage wurde angelegt.');
        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors(['group_name' => $this->meldung()]);
        $this->assertSame($vorher, $this->stand());
    }

    // ---------------------------------------------------------------- Randfall 3 (Einzelrechte unverändert)

    // Randfall 3
    public function test_randfall3_store_einzelrechte_mit_vergebenem_einzelrechtenamen_ergibt_fehler_an_der_zeile(): void
    {
        $vorher = $this->stand();

        $antwort = $this->store(['group_name' => 'Gamma', 'permission_display_new_1' => 'lager_edit'], true);

        $antwort->assertRedirect(route('permission.create'));
        $antwort->assertSessionHasErrors('permission_new_1');
        // Gegenprobe: der Gruppenname ist frei, der Fehler gehört allein der Zeile
        $this->assertFalse(session('errors')->has('group_name'));
        $this->assertSame($vorher, $this->stand());

        $this->store(['group_name' => 'Gamma', 'permission_display_new_1' => 'Frei Eins'], true)->assertRedirect(route('permission'));
        $this->assertSame(['freieins'], $this->rechteNamen((int) DB::table('permissions_groups')->where('group_name', 'gamma')->value('id')));
    }

    // Randfall 3
    public function test_randfall3_update_einzelrechte_mit_vergebenem_einzelrechtenamen_ergibt_fehler_an_der_zeile(): void
    {
        $vorher = $this->stand();

        $antwort = $this->update(self::EINZEL, ['group_name' => 'Gamma', 'permission_display_new_1' => 'beta_read']);

        $antwort->assertRedirect(route('permission.edit', self::EINZEL));
        $antwort->assertSessionHasErrors('permission_new_1');
        $this->assertFalse(session('errors')->has('group_name'));
        $this->assertSame($vorher, $this->stand());

        $this->update(self::EINZEL, ['group_name' => 'Gamma', 'permission_display_new_1' => 'Frei Eins'])->assertRedirect(route('permission'));
        $this->assertSame(1, DB::table('permissions')->where('permission', 'freieins')->count());
    }

    // ---------------------------------------------------------------- Randfall: leer / nicht gesetzt / Liste

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function ungueltigeGruppenNamen(): array
    {
        return [
            'group_name nicht gesendet' => [['group_name' => null]],
            'group_name leer' => [['group_name' => '']],
            'group_name als Liste' => [['group_name' => ['a', 'b']]],
        ];
    }

    // AK2
    #[DataProvider('ungueltigeGruppenNamen')]
    public function test_ak2_store_mit_fehlendem_leerem_oder_listenwert_ergibt_fehler_am_feld_ohne_5xx(array $felder): void
    {
        $vorher = $this->stand();
        $daten = $this->storeFelder($felder);
        if ($felder['group_name'] === null) {
            unset($daten['group_name']);
        }

        $antwort = $this->actingAs($this->dev)->from(route('permission.create'))->post(route('permission.store'), $daten);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.create'));
        $antwort->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        $folge = $this->actingAs($this->dev)->from(route('permission.create'))->followingRedirects()->post(route('permission.store'), $daten);
        $this->assertLessThan(500, $folge->getStatusCode());

        // Gegenprobe: mit gültigem Namen legt dieselbe Anfrage an
        $this->store(['group_name' => 'Gamma'])->assertRedirect(route('permission'));
    }

    // AK4
    #[DataProvider('ungueltigeGruppenNamen')]
    public function test_ak4_update_mit_fehlendem_leerem_oder_listenwert_ergibt_fehler_am_feld_ohne_5xx(array $felder): void
    {
        $vorher = $this->stand();
        $daten = $this->updateFelder(self::ALPHA, $felder);
        if ($felder['group_name'] === null) {
            unset($daten['group_name']);
        }
        $url = route('permission.update', self::ALPHA);

        $antwort = $this->actingAs($this->dev)->from(route('permission.edit', self::ALPHA))->post($url, $daten);

        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertRedirect(route('permission.edit', self::ALPHA));
        $antwort->assertSessionHasErrors('group_name');
        $this->assertSame($vorher, $this->stand());

        $folge = $this->actingAs($this->dev)->from(route('permission.edit', self::ALPHA))->followingRedirects()->post($url, $daten);
        $this->assertLessThan(500, $folge->getStatusCode());

        // Gegenprobe: mit gültigem Namen ändert dieselbe Anfrage
        $this->update(self::ALPHA, ['group_name' => 'Gamma'])->assertRedirect(route('permission'));
    }

    // AK2
    public function test_ak2_store_mit_liste_als_anzeige_ergibt_fehler_ohne_5xx_bis_in_die_folgeseite(): void
    {
        $vorher = $this->stand();

        $antwort = $this->store(['group_display' => ['a', 'b']]);
        $this->assertLessThan(500, $antwort->getStatusCode());
        $antwort->assertSessionHasErrors('group_display');
        $this->assertSame($vorher, $this->stand());

        $this->assertLessThan(500, $this->storeFolgen(['group_display' => ['a', 'b']])->getStatusCode());
        $this->store([])->assertRedirect(route('permission'));
    }

    // ---------------------------------------------------------------- Randfall 1 (Kollation)

    private function kollationEinrichten(): void
    {
        $pdo = DB::connection()->getPdo();
        $this->assertInstanceOf(Sqlite::class, $pdo);

        $falten = static function (string $text): string {
            $glatt = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC; Lower');

            return (string) $glatt?->transliterate($text);
        };
        $pdo->createCollation('UNICODE_CI', static fn (string $a, string $b): int => strcmp($falten($a), $falten($b)));

        $spalten = ['permissions' => 'permission', 'permissions_groups' => 'group_name'];
        $ddl = [];
        $indizes = [];
        foreach ($spalten as $tabelle => $spalte) {
            $sql = (string) DB::scalar("select sql from sqlite_master where type = 'table' and name = ?", [$tabelle]);
            $ddl[$tabelle] = (string) preg_replace('/"'.$spalte.'" varchar not null/', '"'.$spalte.'" varchar not null collate UNICODE_CI', $sql, 1, $ersetzt);
            $this->assertSame(1, $ersetzt, "Die Spalte {$tabelle}.{$spalte} wurde nicht gefunden.");
            foreach (DB::select("select sql from sqlite_master where type = 'index' and tbl_name = ? and sql is not null", [$tabelle]) as $index) {
                $indizes[] = $index->sql;
            }
        }
        $gruppen = DB::table('permissions_groups')->get()->map(fn ($z): array => (array) $z)->all();
        $rechte = DB::table('permissions')->get()->map(fn ($z): array => (array) $z)->all();

        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('DROP TABLE "permissions"');
        $pdo->exec('DROP TABLE "permissions_groups"');
        $pdo->exec($ddl['permissions_groups']);
        $pdo->exec($ddl['permissions']);
        foreach ($indizes as $index) {
            $pdo->exec($index);
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
        DB::table('permissions_groups')->insert($gruppen);
        DB::table('permissions')->insert($rechte);
    }

    /** Gegenprobe: der Aufbau greift, ein direkter Insert der Variante verletzt den UNIQUE-Index. */
    private function assertKollationGreift(string $tabelle, string $spalte, string $variante): void
    {
        try {
            DB::table($tabelle)->insert([$spalte => $variante, ...($tabelle === 'permissions'
                ? ['permission_display' => 'Direkt', 'group_id' => self::EINZEL, 'crud' => 'single']
                : ['group_display' => 'Direkt '.$variante, 'is_group' => 0]), 'created_at' => now(), 'updated_at' => now()]);
            $this->fail("Der direkte Insert von {$variante} hätte den UNIQUE-Index verletzen müssen.");
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }
    }

    // Randfall 1
    public function test_randfall1_store_crud_vergleicht_rechtenamen_mit_der_kollation_der_datenbank(): void
    {
        $this->recht('cafe_read', 'Cafe lesen', self::EINZEL);
        $this->kollationEinrichten();
        $this->assertKollationGreift('permissions', 'permission', 'café_read');
        $vorher = $this->stand();

        $this->store(['group_name' => 'Café'])->assertSessionHasErrors(['group_name' => $this->meldung()]);
        $this->assertSame($vorher, $this->stand());
    }

    // Randfall 1
    public function test_randfall1_store_vergleicht_den_gruppennamen_mit_der_kollation_der_datenbank(): void
    {
        DB::table('permissions_groups')->insert(['group_name' => 'cafe', 'group_display' => 'Cafe', 'is_group' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->kollationEinrichten();
        $this->assertKollationGreift('permissions_groups', 'group_name', 'café');
        $vorher = $this->stand();

        $this->store(['group_name' => 'Café'])->assertSessionHasErrors(['group_name' => $this->meldung()]);
        $this->assertSame($vorher, $this->stand());
        $this->store(['group_name' => 'Café'], true)->assertSessionHasErrors(['group_name' => $this->meldung()]);
        $this->assertSame($vorher, $this->stand());
    }

    // Randfall 1
    public function test_randfall1_update_vergleicht_den_gruppennamen_mit_der_kollation_der_datenbank(): void
    {
        DB::table('permissions_groups')->insert(['group_name' => 'cafe', 'group_display' => 'Cafe', 'is_group' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->kollationEinrichten();
        $this->assertKollationGreift('permissions_groups', 'group_name', 'café');
        $vorher = $this->stand();

        $this->update(self::ALPHA, ['group_name' => 'Café'])->assertSessionHasErrors(['group_name' => $this->meldung()]);
        $this->assertSame($vorher, $this->stand());
    }

    // Randfall 1
    public function test_randfall1_update_vergleicht_die_rechtenamen_mit_der_kollation_der_datenbank(): void
    {
        $this->recht('cafe_edit', 'Cafe ändern', self::EINZEL);
        $this->kollationEinrichten();
        $this->assertKollationGreift('permissions', 'permission', 'café_edit');
        $vorher = $this->stand();

        $this->update(self::ALPHA, ['group_name' => 'Café'])->assertSessionHasErrors(['group_name' => $this->meldung()]);
        $this->assertSame($vorher, $this->stand());
    }

    // Randfall 1
    public function test_randfall1_eigene_rechte_und_eigene_gruppe_zaehlen_auch_bei_akzentgleichem_namen_nicht_als_kollision(): void
    {
        $this->kollationEinrichten();
        // Gegenprobe: ein fremder Insert des akzentgleichen Namens würde kollidieren
        $this->assertKollationGreift('permissions_groups', 'group_name', 'ALPHÁ');

        $this->update(self::ALPHA, ['group_name' => 'ALPHÁ'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('permission'));

        $erwartet = $this->formatiert('ALPHÁ');
        $this->assertSame($erwartet, DB::table('permissions_groups')->where('id', self::ALPHA)->value('group_name'));
        $this->assertSame([$erwartet.'_create', $erwartet.'_read', $erwartet.'_edit', $erwartet.'_delete'], $this->rechteNamen(self::ALPHA));
    }
}
