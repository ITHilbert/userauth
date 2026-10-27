<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Nachbesserung;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Titel im Lösch-Dialog der Listen. Abgedeckt: AK5 (userauth-nachbesserung).
 */
final class DialogDeleteTitelTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../../Fixtures/views');

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => self::ROLLE_NORMAL, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
        // Rechte, die die Listen-Routen und die Anzeige (Löschen-Knopf) brauchen
        foreach (['user_read', 'user_delete', 'role_read', 'role_delete', 'permission_read', 'permission_delete'] as $i => $recht) {
            DB::table('permissions')->insert([
                'id' => $i + 1, 'permission' => $recht, 'permission_display' => $recht,
                'group_id' => 1, 'crud' => 'read', 'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
            DB::table('role_permission')->insert(['role_id' => self::ROLLE_NORMAL, 'permission_id' => $i + 1]);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function listen(): array
    {
        return [
            'Benutzer (userauth::user.index)' => ['/users', 'Benutzer löschen'],
            'Rollen (userauth::role.index)' => ['/roles', 'Rolle löschen'],
            'Rechte (userauth::permission.index)' => ['/permissions', 'Recht löschen'],
        ];
    }

    private function seiteAbrufen(string $uri): string
    {
        // Die Rechte-Liste verlangt die Rolle `dev` (permissions_middleware), die übrigen Listen die Rechte der Rolle `user`
        $user = User::factory()->create(['role_id' => $uri === '/permissions' ? 1 : self::ROLLE_NORMAL]);

        return (string) $this->actingAs($user)->get($uri)->assertOk()->getContent();
    }

    /**
     * Das öffnende Tag von <dialog-delete …>.
     */
    private function dialogTag(string $html): string
    {
        $this->assertSame(1, preg_match('/<dialog-delete\b[^>]*>/', $html, $treffer), 'Die Seite enthält genau ein <dialog-delete>');

        return $treffer[0];
    }

    // AK5
    #[DataProvider('listen')]
    public function test_ak5_dialog_delete_bekommt_den_titel_im_attribut_titel(string $uri, string $erwarteterTitel): void
    {
        $tag = $this->dialogTag($this->seiteAbrufen($uri));

        $this->assertStringContainsString('titel="'.$erwarteterTitel.'"', $tag);
    }

    // AK5
    #[DataProvider('listen')]
    public function test_ak5_dialog_delete_hat_kein_attribut_title_mehr(string $uri, string $erwarteterTitel): void
    {
        $html = $this->seiteAbrufen($uri);
        $tag = $this->dialogTag($html);

        // Gegenprobe: der Dialog mit seinem Text ist vorhanden, nur das alte Attribut fehlt
        $this->assertStringContainsString('body="', $tag);
        $this->assertDoesNotMatchRegularExpression('/\stitle\s*=/', $tag);
    }
}
