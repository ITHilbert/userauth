<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Support;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use ITHilbert\UserAuth\Tests\Fixtures\User;

/**
 * Gemeinsamer Aufbau der Passkey-Tests (userauth passkey-login, #2220): Konfiguration, Rollen, feste Sitzung,
 * Aufruf der Routen und der echte Registrierungsweg für Benutzer mit Passkey. Wer den Trait einbindet, nutzt
 * RefreshDatabase. Die Challenge liest der Test aus der JSON-Antwort der Optionen-Route; die Sitzung hält ein
 * festes Sitzungs-Cookie.
 */
trait PasskeyTestHilfe
{
    protected const RP_ID = 'app.example.test';

    protected const ORIGIN = 'https://app.example.test';

    protected string $sitzungsId = '';

    /** @var list<string> */
    protected array $logTexte = [];

    /**
     * Die Registrierungs-Helfer bestätigen den Benutzer vorher mit seinem Passwort (userauth passkey-bestaetigung,
     * #2225), damit die übrigen Passkey-Tests den Weg wie vor der Bestätigungspflicht gehen. Tests der Bestätigung
     * selbst schalten das ab (`$passkeyAutoBestaetigen = false`).
     */
    protected bool $passkeyAutoBestaetigen = true;

    /** @var array<string, Carbon> Zeitpunkt der letzten Bestätigung je Sitzung und Benutzer */
    protected array $passkeyBestaetigtAm = [];

    /**
     * @param  array<string, mixed>  $passkey  Werte aus userauth.passkey.*
     */
    protected function passkeyAufbauen(array $passkey = [], string $passwortLogin = 'immer'): void
    {
        foreach (['enabled' => true, 'rp_id' => '', 'rp_name' => '', 'origins' => '', ...$passkey] as $schluessel => $wert) {
            config()->set("userauth.passkey.{$schluessel}", $wert);
        }
        config()->set('userauth.passwort_login', $passwortLogin);
        config()->set('app.url', self::ORIGIN);
        config()->set('app.name', 'Testapp');
        config()->set('userauth.two_factor_enabled', false);
        config()->set('userauth.audit_log_enabled', false);
        config()->set('userauth.redirect_after_login', '/');
        config()->set('userauth.theme', 'tailwind');
        config()->set('logging.default', 'null');
        config()->set('laravelkit.theme', 'tailwind');
        app()->setLocale('de');

        View::addLocation(dirname(__DIR__).'/Fixtures/views');
        View::getFinder()->prependLocation(dirname(__DIR__).'/Fixtures/adminviews');

        $jetzt = now();
        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Entwickler', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Administrator', 'created_at' => $jetzt, 'updated_at' => $jetzt],
            ['id' => 3, 'role' => 'user', 'role_display' => 'Benutzer', 'created_at' => $jetzt, 'updated_at' => $jetzt],
        ]);

        Mail::fake();
        $this->neueSitzung();
        $this->logSammeln();
    }

    protected function passkeyAbbauen(): void
    {
        Carbon::setTestNow();
    }

    /** Neue Sitzung (neues Cookie), niemand angemeldet. */
    protected function neueSitzung(): void
    {
        $this->sitzungsId = bin2hex(random_bytes(20));
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withCookie((string) config('session.cookie'), $this->sitzungsId);
    }

    protected function logSammeln(): void
    {
        $this->logTexte = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e): void {
            $this->logTexte[] = $e->message.' '.json_encode($e->context, JSON_PARTIAL_OUTPUT_ON_ERROR);
        });
    }

    protected function geraet(): PasskeyGeraet
    {
        return new PasskeyGeraet(self::RP_ID, self::ORIGIN);
    }

    /** @param  array<string, mixed>  $attribute */
    protected function nutzer(array $attribute = []): User
    {
        return User::factory()->create(array_merge(['role_id' => 3], $attribute));
    }

    /**
     * @return array<string, mixed>
     */
    protected function loginOptionen(): array
    {
        $optionen = $this->postJson(route('userauth.passkey.login.options'))->assertOk()->json();
        $this->assertNotEmpty($optionen['challenge'] ?? null, 'Die Anmeldeoptionen enthalten keine Challenge.');

        return $optionen;
    }

    /**
     * @return array<string, mixed>
     */
    protected function registrierungsOptionen(): array
    {
        $antwort = $this->postJson(route('userauth.passkey.register.options'));
        if ($this->passkeyAutoBestaetigen && $antwort->status() === 403 && $antwort->json('bestaetigung_erforderlich') === true) {
            $this->passkeyBestaetigenPerPasswort();
            $antwort = $this->postJson(route('userauth.passkey.register.options'));
        }
        $optionen = $antwort->assertOk()->json();
        $this->assertNotEmpty($optionen['challenge'] ?? null, 'Die Registrierungsoptionen enthalten keine Challenge.');

        return $optionen;
    }

    /**
     * Anmeldung per Passkey: Optionen holen (Challenge in die Sitzung), Assertion des Geräts senden.
     *
     * @param  array<string, mixed>  $abweichung
     * @param  array<string, mixed>|null  $optionen
     */
    protected function passkeyAnmelden(PasskeyGeraet $geraet, array $abweichung = [], ?array $optionen = null): TestResponse
    {
        $optionen ??= $this->loginOptionen();

        return $this->postJson(route('userauth.passkey.login'), $geraet->anmeldung($optionen, $abweichung));
    }

    /**
     * Registrierung als angemeldeter Benutzer (echter Weg: Optionen, Antwort des Geräts, `credential` + `name`).
     * Der Benutzer bleibt am Guard angemeldet; danach `neueSitzung()`, wenn ein Gast gebraucht wird.
     *
     * @param  array<string, mixed>  $abweichung
     * @param  array<string, mixed>|null  $optionen
     * @param  array<string, mixed>  $extra  zusätzliche Felder der Anfrage
     */
    protected function passkeyRegistrieren(User $nutzer, PasskeyGeraet $geraet, string $name = 'Handy', array $abweichung = [], ?array $optionen = null, array $extra = []): TestResponse
    {
        $this->actingAs($nutzer);
        if ($optionen === null) {
            $optionen = $this->registrierungsOptionen();
        } else {
            $this->passkeyVorBestaetigen();
        }

        return $this->postJson(route('userauth.passkey.register'), array_merge(
            ['credential' => $geraet->registrierung($optionen, $abweichung), 'name' => $name],
            $extra,
        ));
    }

    /** Bestätigt den angemeldeten Benutzer mit dem Passwort der Factory (`password`). */
    protected function passkeyBestaetigenPerPasswort(): TestResponse
    {
        $nutzer = auth()->user();
        $this->passkeyBestaetigtAm[$this->sitzungsId.'|'.($nutzer?->getAuthIdentifier())] = now();

        return $this->postJson(route('userauth.passkey.bestaetigen'), ['aktuelles_passwort' => 'password']);
    }

    /**
     * Für Registrierungen mit vorgegebenen Optionen (keine Options-Anfrage, die die Challenge ersetzen würde): bestätigt
     * einmal je Sitzung und Benutzer innerhalb von 5 Minuten, damit die Drosselung der Bestätigungsroute nicht greift.
     * Gibt es die Route noch nicht, passiert nichts.
     */
    protected function passkeyVorBestaetigen(): void
    {
        $nutzer = auth()->user();
        if (! $this->passkeyAutoBestaetigen || $nutzer === null || ! Route::has('userauth.passkey.bestaetigen')) {
            return;
        }

        $zuletzt = $this->passkeyBestaetigtAm[$this->sitzungsId.'|'.$nutzer->getAuthIdentifier()] ?? null;
        if ($zuletzt !== null && now()->greaterThanOrEqualTo($zuletzt) && $zuletzt->diffInMinutes(now()) < 5) {
            return;
        }

        $this->passkeyBestaetigenPerPasswort();
    }

    /**
     * Benutzer mit einem Passkey, angelegt über den echten Registrierungsweg; danach ist niemand angemeldet.
     *
     * @param  array<string, mixed>  $attribute
     * @return array{0: User, 1: PasskeyGeraet}
     */
    protected function nutzerMitPasskey(array $attribute = [], string $name = 'Handy'): array
    {
        $nutzer = $this->nutzer($attribute);
        $geraet = $this->geraet();
        $this->passkeyRegistrieren($nutzer, $geraet, $name)->assertSuccessful();
        $this->neueSitzung();

        return [$nutzer, $geraet];
    }

    /** Zusätzlicher Passkey für einen bestehenden Benutzer. */
    protected function passkeyHinzufuegen(User $nutzer, string $name): PasskeyGeraet
    {
        $geraet = $this->geraet();
        $this->passkeyRegistrieren($nutzer, $geraet, $name)->assertSuccessful();
        $this->neueSitzung();

        return $geraet;
    }

    protected function anzahlPasskeys(?int $userId = null): int
    {
        $abfrage = DB::table('user_passkeys');

        return $userId === null ? $abfrage->count() : $abfrage->where('user_id', $userId)->count();
    }

    /**
     * Abgelehnt: 422, JSON mit allgemeiner Meldung, niemand angemeldet.
     */
    protected function assertPasskeyAbgelehnt(TestResponse $antwort): void
    {
        $antwort->assertStatus(422);
        $meldung = $antwort->json('message');
        $this->assertIsString($meldung, 'Die Ablehnung enthält keine `message`.');
        $this->assertNotSame('', trim($meldung));
        $this->assertGuest();
    }

    /**
     * Recht `user_edit` in der Datenbank anlegen (die Rolle `admin` bekommt es nicht nötig, andere Rollen nicht).
     */
    protected function userEditRechtAnlegen(): void
    {
        $jetzt = now();
        DB::table('permissions_groups')->insert(['id' => 1, 'group_name' => 'user', 'group_display' => 'Nutzer', 'is_group' => 0, 'created_at' => $jetzt, 'updated_at' => $jetzt]);
        foreach (['read', 'edit', 'create', 'delete'] as $art) {
            DB::table('permissions')->insert([
                'permission' => 'user_'.$art, 'permission_display' => 'Recht '.$art, 'group_id' => 1, 'crud' => $art,
                'created_at' => $jetzt, 'updated_at' => $jetzt,
            ]);
        }
    }
}
