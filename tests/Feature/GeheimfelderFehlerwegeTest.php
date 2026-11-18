<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\View;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;
use Throwable;

/**
 * Bugfix #1596: Der Geheimfelder-Schutz darf nicht still ausfallen, wenn der gebundene
 * ExceptionHandler keine Methode dontFlash() hat (Dekorierer), und die Fehlerwege
 * "falsches aktuelles Passwort" und "ungültiger Reset-Token" flashen keine Geheimfelder.
 */
final class GeheimfelderFehlerwegeTest extends TestCase
{
    use RefreshDatabase;

    private const AKTUELL = 'Aktuelles-Passwort-1!';

    /**
     * Tests mit „dekoriert“ im Namen bekommen den Dekorierer. Er wird vor dem Boot des Providers gebunden (der echte Handler ist noch unberührt).
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        if (str_contains($this->name(), 'dekoriert')) {
            $echt = $app->make(ExceptionHandler::class);
            $app->instance(ExceptionHandler::class, new HandlerOhneDontFlash($echt));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/../Fixtures/views');
        config()->set('userauth.password_policy.enabled', false);
        config()->set('userauth.two_factor_enabled', false);

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Developer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Admin', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 3, 'role' => 'user', 'role_display' => 'Anwender', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);
    }

    private function normalesFeldWirdGeflasht(): void
    {
        $this->from('/x')->post(route('password.sendtocken'), ['email' => 'kein-email-format'])
            ->assertSessionHasErrors();
        $this->assertSame('kein-email-format', session()->getOldInput('email'), 'Gegenprobe: normales Feld wird geflasht');
    }

    /**
     * Aufbau-Beleg: der gebundene Handler ist der Dekorierer und hat keine dontFlash-Methode.
     */
    private function dekoriererIstGebunden(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $this->assertInstanceOf(HandlerOhneDontFlash::class, $handler);
        $this->assertFalse(method_exists($handler, 'dontFlash'));
    }

    // AK1
    public function test_aktuelles_passwort_wird_trotz_dekoriertem_handler_nicht_geflasht(): void
    {
        $this->dekoriererIstGebunden();
        $nutzer = User::factory()->create(['role_id' => 3, 'password' => Hash::make(self::AKTUELL)]);
        $this->normalesFeldWirdGeflasht();

        $this->actingAs($nutzer)->from(route('password.edit'))->post(route('password.update'), [
            'aktuelles_passwort' => self::AKTUELL,
            'password' => 'x',
            'password_confirmation' => 'y',
        ])->assertSessionHasErrors('password');

        $this->assertArrayNotHasKey('aktuelles_passwort', session()->getOldInput(), '_old_input enthält aktuelles_passwort');
    }

    // AK1
    public function test_pwtoken_wird_trotz_dekoriertem_handler_nicht_geflasht(): void
    {
        $this->dekoriererIstGebunden();
        $this->normalesFeldWirdGeflasht();

        $this->from('/x')->post(route('password.updatewithtoken'), [
            'email' => 'max@firma.de',
            'pwtoken' => 'geheimer-token-123',
            'password' => 'x',
            'password_confirmation' => 'y',
        ])->assertSessionHasErrors('password');

        $alt = session()->getOldInput();
        $this->assertSame('max@firma.de', $alt['email'] ?? null, 'Gegenprobe: email wird geflasht');
        $this->assertArrayNotHasKey('pwtoken', $alt, '_old_input enthält pwtoken');
    }

    // AK1
    public function test_zwei_faktor_code_wird_trotz_dekoriertem_handler_nicht_geflasht(): void
    {
        $this->dekoriererIstGebunden();
        $this->normalesFeldWirdGeflasht();

        $this->from('/x')->post(route('verify.store'), ['two_factor_code' => str_repeat('1', 21)])
            ->assertSessionHasErrors('two_factor_code');

        $this->assertArrayNotHasKey('two_factor_code', session()->getOldInput(), '_old_input enthält two_factor_code');
    }

    // AK2
    public function test_falsches_aktuelles_passwort_flasht_es_nicht(): void
    {
        $nutzer = User::factory()->create(['role_id' => 3, 'password' => Hash::make(self::AKTUELL)]);
        $this->normalesFeldWirdGeflasht();

        $this->actingAs($nutzer)->from(route('password.edit'))->post(route('password.update'), [
            'aktuelles_passwort' => 'Falsches-Passwort-9!',
            'password' => 'Neues-Passwort-2!',
            'password_confirmation' => 'Neues-Passwort-2!',
        ])->assertSessionHasErrors('aktuelles_passwort');

        $alt = session()->getOldInput();
        $this->assertArrayNotHasKey('aktuelles_passwort', $alt, '_old_input enthält aktuelles_passwort');
        $this->assertArrayNotHasKey('password', $alt);
    }

    // AK3
    public function test_ungueltiger_token_flasht_pwtoken_nicht(): void
    {
        $this->normalesFeldWirdGeflasht();

        $this->from('/x')->post(route('password.updatewithtoken'), [
            'email' => 'max@firma.de',
            'pwtoken' => 'ungueltiger-token-123',
            'password' => 'Neues-Passwort-2!',
            'password_confirmation' => 'Neues-Passwort-2!',
        ])->assertRedirect(route('password.forgotten'))
            ->assertSessionHasErrors('pwtoken');

        $alt = session()->getOldInput();
        $this->assertSame('max@firma.de', $alt['email'] ?? null, 'Gegenprobe: email wird geflasht');
        $this->assertArrayNotHasKey('pwtoken', $alt, '_old_input enthält pwtoken');
    }

    // AK4
    public function test_wiederverwendetes_passwort_flasht_aktuelles_passwort_bei_dekoriertem_handler_nicht(): void
    {
        $this->dekoriererIstGebunden();
        config()->set('userauth.password_policy.enabled', true);
        $nutzer = User::factory()->create(['role_id' => 3, 'password' => Hash::make(self::AKTUELL)]);

        $this->actingAs($nutzer)->from(route('password.edit'))->post(route('password.update'), [
            'aktuelles_passwort' => self::AKTUELL,
            'password' => self::AKTUELL,
            'password_confirmation' => self::AKTUELL,
            'harmlos' => 'sichtbar',
        ])->assertSessionHasErrors(['password' => trans('userauth::password.bereits_verwendet')]);

        $alt = session()->getOldInput();
        $this->assertSame('sichtbar', $alt['harmlos'] ?? null, 'Gegenprobe: normales Feld wird geflasht');
        $this->assertArrayNotHasKey('aktuelles_passwort', $alt, '_old_input enthält aktuelles_passwort');
    }

    // AK5
    public function test_wiederverwendetes_passwort_flasht_pwtoken_bei_dekoriertem_handler_nicht(): void
    {
        $this->dekoriererIstGebunden();
        config()->set('userauth.password_policy.enabled', true);
        $nutzer = User::factory()->create(['role_id' => 3, 'email' => 'max@firma.de', 'password' => Hash::make(self::AKTUELL)]);
        $token = Password::broker()->createToken($nutzer);

        $this->from('/x')->post(route('password.updatewithtoken'), [
            'email' => 'max@firma.de',
            'pwtoken' => $token,
            'password' => self::AKTUELL,
            'password_confirmation' => self::AKTUELL,
        ])->assertSessionHasErrors(['password' => trans('userauth::password.bereits_verwendet')]);

        $alt = session()->getOldInput();
        $this->assertSame('max@firma.de', $alt['email'] ?? null, 'Gegenprobe: email wird geflasht');
        $this->assertArrayNotHasKey('pwtoken', $alt, '_old_input enthält pwtoken');
    }

    // AK6
    public function test_falsches_aktuelles_passwort_flasht_es_bei_dekoriertem_handler_nicht(): void
    {
        $this->dekoriererIstGebunden();
        $nutzer = User::factory()->create(['role_id' => 3, 'password' => Hash::make(self::AKTUELL)]);

        $this->actingAs($nutzer)->from(route('password.edit'))->post(route('password.update'), [
            'aktuelles_passwort' => 'Falsches-Passwort-9!',
            'password' => 'Neues-Passwort-2!',
            'password_confirmation' => 'Neues-Passwort-2!',
            'harmlos' => 'sichtbar',
        ])->assertSessionHasErrors('aktuelles_passwort');

        $alt = session()->getOldInput();
        $this->assertSame('sichtbar', $alt['harmlos'] ?? null, 'Gegenprobe: normales Feld wird geflasht');
        $this->assertArrayNotHasKey('aktuelles_passwort', $alt, '_old_input enthält aktuelles_passwort');
    }

    // AK6
    public function test_ungueltiger_token_flasht_pwtoken_bei_dekoriertem_handler_nicht(): void
    {
        $this->dekoriererIstGebunden();

        $this->from('/x')->post(route('password.updatewithtoken'), [
            'email' => 'max@firma.de',
            'pwtoken' => 'ungueltiger-token-123',
            'password' => 'Neues-Passwort-2!',
            'password_confirmation' => 'Neues-Passwort-2!',
        ])->assertRedirect(route('password.forgotten'))
            ->assertSessionHasErrors('pwtoken');

        $alt = session()->getOldInput();
        $this->assertSame('max@firma.de', $alt['email'] ?? null, 'Gegenprobe: email wird geflasht');
        $this->assertArrayNotHasKey('pwtoken', $alt, '_old_input enthält pwtoken');
    }
}

/**
 * Dekorierer ohne dontFlash(), reicht alles an den echten Handler weiter (wie z. B. ein Adapter).
 */
final class HandlerOhneDontFlash implements ExceptionHandler
{
    public function __construct(private readonly ExceptionHandler $echt) {}

    public function report(Throwable $e): void
    {
        $this->echt->report($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->echt->shouldReport($e);
    }

    public function render($request, Throwable $e)
    {
        return $this->echt->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->echt->renderForConsole($output, $e);
    }
}
