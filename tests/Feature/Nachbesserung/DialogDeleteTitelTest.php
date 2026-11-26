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
 * Titel im Lösch-Dialog der Listen. Abgedeckt: AK5 (userauth-nachbesserung), umgestellt auf das Blade-Markup (kern-umstellung AK10).
 */
final class DialogDeleteTitelTest extends TestCase
{
    use RefreshDatabase;

    private const ROLLE_NORMAL = 3;

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../../Fixtures/views');
        config(['laravelkit.theme' => 'kern']);

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
        // Die Rechte-Liste verlangt die Rolle `admin` (permissions_middleware), die übrigen Listen die Rechte der Rolle `user`
        $user = User::factory()->create(['role_id' => $uri === '/permissions' ? 2 : self::ROLLE_NORMAL]);

        return (string) $this->actingAs($user)->get($uri)->assertOk()->getContent();
    }

    /**
     * Der Dialog (Blade-Markup der Partial `partials/loeschen`, seit kern-umstellung ohne Vue-Insel).
     */
    private function dialog(string $html): \DOMElement
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $dialoge = (new \DOMXPath($dom))->query('//div[contains(concat(" ", normalize-space(@class), " "), " kit-dialog__content ")][@role="dialog"]');
        $this->assertSame(1, $dialoge->length, 'Die Seite enthält genau einen Lösch-Dialog');

        return $dialoge->item(0);
    }

    // AK5 (userauth-nachbesserung), Randfall „Titel steht in data-props“ (userauth-admin-views)
    #[DataProvider('listen')]
    public function test_ak5_dialog_bekommt_den_titel_als_titel(string $uri, string $erwarteterTitel): void
    {
        $dialog = $this->dialog($this->seiteAbrufen($uri));

        $titel = (new \DOMXPath($dialog->ownerDocument))->query('.//*[contains(concat(" ", normalize-space(@class), " "), " kit-dialog__title ")]', $dialog);
        $this->assertSame(1, $titel->length);
        $this->assertSame($erwarteterTitel, trim($titel->item(0)->textContent));
    }

    // AK5 (userauth-nachbesserung)
    #[DataProvider('listen')]
    public function test_ak5_dialog_hat_kein_attribut_title(string $uri, string $erwarteterTitel): void
    {
        $html = $this->seiteAbrufen($uri);

        // Gegenprobe: der Dialog mit seinem Titel ist vorhanden, nur das Attribut title fehlt
        $dialog = $this->dialog($html);
        $this->assertSame($erwarteterTitel, trim((new \DOMXPath($dialog->ownerDocument))->query('.//*[contains(concat(" ", normalize-space(@class), " "), " kit-dialog__title ")]', $dialog)->item(0)->textContent));
        $this->assertFalse($dialog->hasAttribute('title'));
        $this->assertStringNotContainsString('<dialog-delete', $html);
    }
}
