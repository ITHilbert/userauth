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

/**
 * Rechtegruppen in den Rollenformularen. Abgedeckt: AK1, AK2 (userauth-rechtegruppen-filter, Dashboard #257).
 */
final class RechtegruppenFilterTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    private const ROLLE_ZIEL = 4;

    /** @var array<string, int> */
    private array $rechteIds = [];

    private int $naechsteRechteId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');

        $jetzt = now();
        foreach ([[1, 'dev'], [2, 'admin'], [self::ROLLE_NORMAL, 'user'], [self::ROLLE_ZIEL, 'editor']] as [$id, $name]) {
            DB::table('roles')->insert([
                'id' => $id, 'role' => $name, 'role_display' => ucfirst($name),
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }

        $this->gruppe(1, 'user', 'Gruppe Nutzer', 0, ['create', 'read', 'edit', 'delete']);
        $this->gruppe(2, 'role', 'Gruppe Rollen', 0, ['create', 'read', 'edit', 'delete']);

        foreach (['role_create', 'role_read', 'role_edit'] as $recht) {
            DB::table('role_permission')->insert([
                'role_id' => self::ROLLE_NORMAL,
                'permission_id' => $this->rechteIds[$recht],
            ]);
        }
    }

    /**
     * @param  list<string>  $arten  crud-Arten (Gruppe mit is_group 0) oder ['single'] bei is_group 1
     */
    private function gruppe(int $id, string $name, string $anzeige, int $istGruppe, array $arten): void
    {
        $jetzt = now();
        DB::table('permissions_groups')->insert([
            'id' => $id, 'group_name' => $name, 'group_display' => $anzeige, 'is_group' => $istGruppe,
            'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);

        foreach ($arten as $art) {
            $recht = $istGruppe === 1 ? $name.'_einzel' : $name.'_'.$art;
            $rechtId = $this->naechsteRechteId++;
            DB::table('permissions')->insert([
                'id' => $rechtId, 'permission' => $recht, 'permission_display' => 'Recht '.$recht,
                'group_id' => $id, 'crud' => $art, 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            $this->rechteIds[$recht] = $rechtId;
        }
    }

    private function hostGruppeMitId3(): void
    {
        $this->gruppe(3, 'lizenz', 'Gruppe Lizenzen', 0, ['create', 'read', 'edit', 'delete']);
    }

    private function berechtigterNutzer(): User
    {
        return User::factory()->create(['role_id' => self::ROLLE_NORMAL]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function formulare(): array
    {
        return ['Rolle anlegen' => ['anlegen'], 'Rolle bearbeiten' => ['bearbeiten']];
    }

    private function formularOeffnen(string $formular): TestResponse
    {
        $url = $formular === 'anlegen' ? route('role.create') : route('role.edit', self::ROLLE_ZIEL);

        return $this->actingAs($this->berechtigterNutzer())->get($url);
    }

    // AK1
    #[DataProvider('formulare')]
    public function test_host_gruppe_mit_id_3_erscheint_im_formular(string $formular): void
    {
        $this->hostGruppeMitId3();

        $antwort = $this->formularOeffnen($formular)->assertOk();

        // Gegenprobe: die Standardgruppen sind im selben Formular da
        $antwort->assertSee('Gruppe Nutzer')->assertSee('Gruppe Rollen');
        $antwort->assertSee('Gruppe Lizenzen');
    }

    // AK1
    #[DataProvider('formulare')]
    public function test_recht_der_host_gruppe_mit_id_3_erscheint_im_formular(string $formular): void
    {
        $this->hostGruppeMitId3();

        $this->formularOeffnen($formular)
            ->assertOk()
            ->assertSee('name="permission['.$this->rechteIds['lizenz_read'].']"', false);
    }

    // AK1
    public function test_anlegen_mit_recht_der_host_gruppe_weist_es_der_rolle_zu(): void
    {
        $this->hostGruppeMitId3();

        $this->actingAs($this->berechtigterNutzer())
            ->post(route('role.store'), [
                'role_display' => 'Lizenzler', 'role' => 'lizenzler',
                'permission' => [$this->rechteIds['lizenz_read']],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('role'));

        $rolleId = DB::table('roles')->where('role', 'lizenzler')->value('id');
        $this->assertNotNull($rolleId);
        $this->assertSame(
            [$this->rechteIds['lizenz_read']],
            DB::table('role_permission')->where('role_id', $rolleId)->pluck('permission_id')->map(fn ($i): int => (int) $i)->all()
        );
    }

    // AK1
    public function test_bearbeiten_mit_recht_der_host_gruppe_weist_es_der_rolle_zu(): void
    {
        $this->hostGruppeMitId3();

        $this->actingAs($this->berechtigterNutzer())
            ->post(route('role.update', self::ROLLE_ZIEL), [
                'role_display' => 'Editor neu', 'role' => 'editor-neu',
                'permission' => [$this->rechteIds['lizenz_read']],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('role'));

        $this->assertSame(
            [$this->rechteIds['lizenz_read']],
            DB::table('role_permission')->where('role_id', self::ROLLE_ZIEL)->pluck('permission_id')->map(fn ($i): int => (int) $i)->all()
        );
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function formulareUndPermissionIds(): array
    {
        return [
            'anlegen, permission id 3' => ['anlegen', 3],
            'anlegen, permission id 7' => ['anlegen', 7],
            'bearbeiten, permission id 3' => ['bearbeiten', 3],
            'bearbeiten, permission id 7' => ['bearbeiten', 7],
        ];
    }

    // AK2
    #[DataProvider('formulareUndPermissionIds')]
    public function test_gruppe_permission_erscheint_nicht_andere_gruppen_schon(string $formular, int $permissionGruppeId): void
    {
        $this->gruppe($permissionGruppeId, 'permission', 'Gruppe Entwickler', 1, ['single']);
        // Gegenprobe: Host-Gruppe auf freier id erscheint in derselben Seite
        $this->gruppe($permissionGruppeId === 3 ? 8 : 3, 'lizenz', 'Gruppe Lizenzen', 0, ['create', 'read', 'edit', 'delete']);

        $antwort = $this->formularOeffnen($formular)->assertOk();

        $antwort->assertSee('Gruppe Nutzer')->assertSee('Gruppe Rollen')->assertSee('Gruppe Lizenzen');
        $antwort->assertDontSee('Gruppe Entwickler');
        $antwort->assertDontSee('name="permission['.$this->rechteIds['permission_einzel'].']"', false);
    }

    // AK2
    #[DataProvider('formulare')]
    public function test_gruppe_permission_mit_id_7_blendet_gruppe_mit_id_3_nicht_aus(string $formular): void
    {
        $this->gruppe(7, 'permission', 'Gruppe Entwickler', 1, ['single']);
        $this->hostGruppeMitId3();

        $this->formularOeffnen($formular)
            ->assertOk()
            ->assertSee('Gruppe Lizenzen')
            ->assertDontSee('Gruppe Entwickler');
    }

    // Randfall 1
    #[DataProvider('formulare')]
    public function test_ohne_gruppe_permission_erscheinen_alle_gruppen(string $formular): void
    {
        $this->hostGruppeMitId3();
        $this->gruppe(4, 'bericht', 'Gruppe Berichte', 1, ['single']);

        $this->formularOeffnen($formular)
            ->assertOk()
            ->assertSee('Gruppe Nutzer')
            ->assertSee('Gruppe Rollen')
            ->assertSee('Gruppe Lizenzen')
            ->assertSee('Gruppe Berichte');
    }
}
