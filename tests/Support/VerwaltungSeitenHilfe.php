<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;

/**
 * Gemeinsamer Aufbau der Tests zu den Verwaltungsseiten (kern-umstellung): Host-Layout aus einem Fixture-Ordner,
 * Rollen, Rechte, ein Admin und die Adressen der zehn Seiten. Die Datei, die den Trait einbindet, braucht `RefreshDatabase`.
 */
trait VerwaltungSeitenHilfe
{
    protected User $admin;

    protected User $ziel;

    /**
     * @param  string  $layoutOrdner  Ordner unter tests/Fixtures mit `layouts/app.blade.php` des Hosts
     */
    protected function verwaltungAufbauen(string $layoutOrdner, string $theme = 'kern'): void
    {
        View::addLocation(dirname(__DIR__).'/Fixtures/views');
        View::getFinder()->prependLocation(dirname(__DIR__).'/Fixtures/'.$layoutOrdner);
        config(['laravelkit.theme' => $theme, 'userauth.password_policy.enabled' => false]);

        $jetzt = now();
        foreach ([[1, 'admin', 'Admin'], [4, 'editor', 'Editor']] as [$id, $name, $anzeige]) {
            DB::table('roles')->insert(['id' => $id, 'role' => $name, 'role_display' => $anzeige, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        }
        DB::table('permissions_groups')->insert([
            'id' => 1, 'group_name' => 'user', 'group_display' => 'Gruppe Nutzer', 'is_group' => 0,
            'created_at' => $jetzt, 'updated_at' => $jetzt,
        ]);
        foreach (['create', 'read', 'edit', 'delete'] as $art) {
            DB::table('permissions')->insert([
                'permission' => 'user_'.$art, 'permission_display' => 'Recht '.$art,
                'group_id' => 1, 'crud' => $art, 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }

        $this->admin = User::factory()->create(['role_id' => 1, 'name' => 'Admin Nutzer', 'email' => 'admin@example.test']);
        $this->ziel = User::factory()->create(['role_id' => 4, 'name' => 'Ziel Nutzer', 'email' => 'ziel@example.test']);
    }

    /** @return array<string, array{0: string}> */
    public static function verwaltungSeiten(): array
    {
        $faelle = [];
        foreach (['user', 'user.create', 'user.edit', 'user.icon', 'role', 'role.create', 'role.edit', 'permission', 'permission.create', 'permission.edit'] as $seite) {
            $faelle[$seite] = [$seite];
        }

        return $faelle;
    }

    protected function verwaltungUrl(string $seite): string
    {
        return match ($seite) {
            'user' => route('user'),
            'user.create' => route('user.create'),
            'user.edit' => route('user.edit', $this->ziel->id),
            'user.icon' => route('usericon.edit'),
            'role' => route('role'),
            'role.create' => route('role.create'),
            'role.edit' => route('role.edit', 4),
            'permission' => route('permission'),
            'permission.create' => route('permission.create'),
            'permission.edit' => route('permission.edit', 1),
        };
    }

    protected function verwaltungSeite(string $seite): string
    {
        return $this->actingAs($this->admin)->get($this->verwaltungUrl($seite))->assertOk()->getContent();
    }

    protected function verwaltungDom(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }
}
