<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\Haertung;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Paket-Migrationen leeren "Angemeldet bleiben" aus der Zeit vor dem Update. Abgedeckt: AK14a (userauth-haertung).
 *
 * Aufbau: Alle Paket-Migrationen laufen auf leerer Datenbank; die eine Migration, die zu AK14a gehört (der Name ist
 * bewusst nicht festgelegt), wird danach aus der Migrationshistorie genommen. Das ergibt den Stand "vor dem Update":
 * volles Schema, Nutzer mit `remember_token`, die Migration steht noch aus.
 */
final class RememberTokenMigrationTest extends TestCase
{
    /** Migrationen, die diese Aufgabe nicht betreffen (Stand vor AK14a). */
    private const BEKANNTE_MIGRATIONEN = [
        '2019_10_12_000000_create_my_users_table',
        '2020_04_23_133628_create_roles_table',
        '2020_04_23_133642_create_permissions_table',
        '2020_04_23_133643_create_permissions_groups_table',
        '2020_04_23_140937_create_role_permission_table',
        '2026_02_17_000000_create_user_auth_audit_logs_table',
        '2026_02_17_100000_add_2fa_columns_to_users_table',
        '2026_03_28_000000_create_password_policy_tables',
        '2026_03_28_000001_create_teams_tables',
        '2026_09_29_000000_create_user_social_accounts_table',
        '2026_09_29_000001_create_user_pending_social_links_table',
        '2026_10_23_000000_make_role_id_nullable_on_users_table',
        '2026_10_23_100000_create_password_reset_tokens_table',
    ];

    protected function defineDatabaseMigrations(): void
    {
        // bewusst leer: der Test steuert die Migrationsreihenfolge selbst
    }

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../../Fixtures/views');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('userauth.two_factor_enabled', false);
        $app['config']->set('userauth.audit_log_enabled', false);
        $app['config']->set('userauth.password_policy.enabled', false);
    }

    // ---------------------------------------------------------------- Hilfen

    private function paketMigrationenAusfuehren(): void
    {
        $exit = Artisan::call('migrate', [
            '--path' => [dirname(__DIR__, 3).'/src/Database/Migrations'],
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
    }

    private function paketMigrationenZurueckrollen(): void
    {
        $exit = Artisan::call('migrate:rollback', [
            '--path' => [dirname(__DIR__, 3).'/src/Database/Migrations'],
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
    }

    /**
     * Stand vor dem Update: Nutzer 11 und 12 (und 14, gelöscht) mit Angemeldet-bleiben, Nutzer 13 ohne.
     */
    private function standVorDemUpdate(): void
    {
        $this->paketMigrationenAusfuehren();

        $neu = DB::table('migrations')->whereNotIn('migration', self::BEKANNTE_MIGRATIONEN)->pluck('migration');
        $this->assertCount(1, $neu, 'Es muss genau eine neue Paket-Migration für AK14a geben, gefunden: '.$neu->implode(', '));
        DB::table('migrations')->whereIn('migration', $neu->all())->delete();

        $jetzt = now();
        foreach ([
            [11, 'Anna', 'anna@firma.de', 'token-anna', null],
            [12, 'Bernd', 'bernd@firma.de', 'token-bernd', null],
            [13, 'Clara', 'clara@firma.de', null, null],
            [14, 'Dirk', 'dirk@firma.de', 'token-dirk', $jetzt],
        ] as [$id, $name, $email, $token, $geloescht]) {
            DB::table('users')->insert([
                'id' => $id, 'role_id' => 3, 'name' => $name, 'email' => $email, 'email_verified_at' => $jetzt,
                'password' => Hash::make('password'), 'remember_token' => $token, 'deleted_at' => $geloescht,
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }

        $this->assertSame('token-anna', DB::table('users')->where('id', 11)->value('remember_token'));
    }

    private function token(int $id): ?string
    {
        $wert = DB::table('users')->where('id', $id)->value('remember_token');

        return $wert === null ? null : (string) $wert;
    }

    /**
     * Zeilen ohne `remember_token`, je Nutzer-ID.
     *
     * @return array<int, array<string, mixed>>
     */
    private function nutzerOhneToken(): array
    {
        $zeilen = [];
        foreach (DB::table('users')->orderBy('id')->get() as $zeile) {
            $daten = (array) $zeile;
            unset($daten['remember_token']);
            $zeilen[(int) $daten['id']] = $daten;
        }

        return $zeilen;
    }

    private function recallerCookieName(): string
    {
        return $this->app['auth']->guard()->getRecallerName();
    }

    private function recallerCookieWert(int $id, string $token): string
    {
        return $id.'|'.$token.'|'.DB::table('users')->where('id', $id)->value('password');
    }

    /**
     * Anfrage eines frischen Browsers, der nur das Angemeldet-bleiben-Cookie mitschickt.
     */
    private function mitRecallerAufrufen(int $id, string $token): TestResponse
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->app['cookie']->flushQueuedCookies();

        return $this->withCookie($this->recallerCookieName(), $this->recallerCookieWert($id, $token))
            ->get(route('password.edit'));
    }

    // ----------------------------------------------------------------- AK14a

    // AK14a
    public function test_nach_den_migrationen_ist_jedes_remember_token_geleert(): void
    {
        $this->standVorDemUpdate();

        $this->paketMigrationenAusfuehren();

        foreach ([11, 12, 13, 14] as $id) {
            $this->assertNull($this->token($id), "Nutzer {$id} hat noch ein remember_token.");
        }
        $this->assertSame(0, DB::table('users')->whereNotNull('remember_token')->count());
    }

    // AK14a (auch gelöschte Nutzer; der Nutzer ohne Token bleibt ohne Token)
    public function test_das_leeren_betrifft_auch_gelöschte_nutzer_und_laesst_leere_tokens_leer(): void
    {
        $this->standVorDemUpdate();
        $this->assertSame('token-dirk', $this->token(14));
        $this->assertNull($this->token(13));

        $this->paketMigrationenAusfuehren();

        $this->assertNull($this->token(14));
        $this->assertNull($this->token(13));
    }

    // AK14a (Gegenprobe: das Cookie ist gültig aufgebaut und meldet vor der Migration an)
    public function test_ein_altes_angemeldet_bleiben_cookie_meldet_vor_der_migration_an(): void
    {
        $this->standVorDemUpdate();

        $this->mitRecallerAufrufen(11, 'token-anna')->assertOk();

        $this->assertAuthenticatedAs(User::query()->findOrFail(11));
    }

    // AK14a
    public function test_ein_altes_angemeldet_bleiben_cookie_meldet_nach_der_migration_niemanden_mehr_an(): void
    {
        $this->standVorDemUpdate();
        $this->mitRecallerAufrufen(11, 'token-anna')->assertOk();
        $this->assertAuthenticatedAs(User::query()->findOrFail(11));

        $this->paketMigrationenAusfuehren();

        $this->mitRecallerAufrufen(11, 'token-anna')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->mitRecallerAufrufen(12, 'token-bernd')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // AK14a (Gegenprobe: ein nach der Migration neu gesetztes Angemeldet-bleiben meldet an)
    public function test_ein_nach_der_migration_neu_gesetztes_angemeldet_bleiben_funktioniert(): void
    {
        $this->standVorDemUpdate();
        $this->paketMigrationenAusfuehren();
        $this->assertNull($this->token(11));

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $antwort = $this->post(route('login'), ['email' => 'anna@firma.de', 'password' => 'password', 'remember' => 'on']);
        $this->assertAuthenticatedAs(User::query()->findOrFail(11));
        $this->assertNotNull($this->token(11), 'Beim Anmelden mit "Angemeldet bleiben" wird ein neues Token gesetzt.');
        $cookie = collect($antwort->headers->getCookies())->first(fn ($c): bool => $c->getName() === $this->recallerCookieName());
        $this->assertNotNull($cookie, 'Das Angemeldet-bleiben-Cookie fehlt.');

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->app['cookie']->flushQueuedCookies();
        $this->withUnencryptedCookie($this->recallerCookieName(), $cookie->getValue())->get(route('password.edit'))->assertOk();

        $this->assertAuthenticatedAs(User::query()->findOrFail(11));
    }

    // AK14a
    public function test_alle_uebrigen_nutzerdaten_bleiben_unveraendert(): void
    {
        $this->standVorDemUpdate();
        $vorher = $this->nutzerOhneToken();
        $this->assertCount(4, $vorher);

        $this->paketMigrationenAusfuehren();

        $this->assertNull($this->token(11), 'Gegenprobe: Die Migration ist gelaufen.');
        $this->assertSame($vorher, $this->nutzerOhneToken());
    }

    // AK14a (erneutes Ausführen leert ein später gesetztes Token nicht erneut)
    public function test_erneutes_ausfuehren_der_migrationen_schadet_nicht(): void
    {
        $this->standVorDemUpdate();
        $this->paketMigrationenAusfuehren();
        $this->assertNull($this->token(11), 'Gegenprobe: Die Migration ist gelaufen.');
        DB::table('users')->where('id', 12)->update(['remember_token' => 'neues-token']);
        $vorher = $this->nutzerOhneToken();

        $this->paketMigrationenAusfuehren();

        $this->assertSame('neues-token', $this->token(12));
        $this->assertNull($this->token(11));
        $this->assertSame($vorher, $this->nutzerOhneToken());
    }

    // AK14a (Entscheidung 0007/0009: down() ohne Rückbau, keine Nutzer gelöscht oder geändert)
    public function test_das_zurueckrollen_loescht_und_aendert_keine_nutzer(): void
    {
        $this->standVorDemUpdate();
        $this->paketMigrationenAusfuehren();
        $this->assertNull($this->token(11), 'Gegenprobe: Die Migration ist gelaufen.');
        $vorher = $this->nutzerOhneToken();

        $this->paketMigrationenZurueckrollen();

        // Andere Paket-Migrationen entfernen beim Zurückrollen ihre eigenen Spalten; verglichen wird, was bleibt.
        $nachher = $this->nutzerOhneToken();
        $this->assertSame(array_keys($vorher), array_keys($nachher), 'Nutzer wurden gelöscht oder hinzugefügt.');
        foreach ($nachher as $id => $zeile) {
            foreach (['name', 'email', 'password', 'role_id', 'email_verified_at', 'created_at', 'deleted_at'] as $spalte) {
                $this->assertArrayHasKey($spalte, $zeile, "Spalte {$spalte} fehlt nach dem Zurückrollen.");
            }
            $this->assertSame(array_intersect_key($vorher[$id], $zeile), $zeile, "Nutzer {$id} wurde geändert.");
        }
        $this->assertSame(0, DB::table('users')->whereNotNull('remember_token')->count());
    }
}
