<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Anzeige „löschen“ statt „delete“ für das CRUD-Recht delete (Dashboard #527, userauth-seeder AK2).
 */
final class DeleteAnzeigeTest extends TestCase
{
    use RefreshDatabase;

    // AK2
    public function test_deutsche_sprachdatei_nennt_delete_loeschen(): void
    {
        app()->setLocale('de');

        $this->assertSame('löschen', __('userauth::permission.delete'));
    }

    // AK2
    public function test_neue_crud_gruppe_bekommt_anzeige_loeschen_und_internen_namen_delete(): void
    {
        app()->setLocale('de');
        $jetzt = now();
        DB::table('roles')->insert(['id' => 1, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        $dev = User::factory()->create(['role_id' => 1, 'name' => 'Dev Nutzer', 'email' => 'dev@example.test']);

        $this->actingAs($dev)->post(route('permission.store'), [
            'group_display' => 'Berichte', 'group_name' => 'berichte',
        ]);

        $recht = DB::table('permissions')->where('permission', 'berichte_delete')->whereNull('deleted_at')->first();
        $this->assertNotNull($recht, 'Recht berichte_delete wurde nicht angelegt');
        $this->assertSame('Berichte löschen', $recht->permission_display);
        $this->assertSame('delete', $recht->crud);
    }
}
