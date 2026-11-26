<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * Gemeinsamer Aufbau der OIDC-Tests: Konfiguration, Rollen, feste Sitzungs-ID (damit state/nonce wirklich
 * zwischen Redirect und Callback in der Sitzung liegen), Aufruf der Routen.
 * Wer den Trait einbindet, nutzt RefreshDatabase.
 */
trait OidcTestHilfe
{
    protected string $sitzungsId = '';

    /** @var list<string> */
    protected array $logTexte = [];

    /**
     * @param  array<string, mixed>  $ueberschreibe  Werte aus userauth.oidc.*
     */
    protected function oidcKonfigurieren(array $ueberschreibe = []): void
    {
        $werte = [
            'enabled' => true,
            'issuer' => 'https://auth.example.test',
            'client_id' => 'client-app',
            'client_secret' => 'SECRET-geheim-123',
            'ca_bundle' => '',
            'button_text' => 'Mit IT-Hilbert anmelden',
            'auto_create' => false,
            'default_role' => 'user',
            'group_roles' => '',
            'auto_redirect' => false,
            ...$ueberschreibe,
        ];

        foreach ($werte as $schluessel => $wert) {
            config()->set("userauth.oidc.{$schluessel}", $wert);
        }

        config()->set('userauth.two_factor_enabled', false);
        config()->set('userauth.audit_log_enabled', false);
        config()->set('userauth.redirect_after_login', '/');
        config()->set('logging.default', 'null');

        DB::table('roles')->insert([
            ['id' => 1, 'role' => 'dev', 'role_display' => 'Entwickler', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'role' => 'admin', 'role_display' => 'Administrator', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'role' => 'user', 'role_display' => 'Benutzer', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Mail::fake();
    }

    /**
     * @return class-string<Model>
     */
    protected function userModel(): string
    {
        return config('auth.providers.users.model');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(array $attributes = [], bool $verified = true): Model
    {
        $factory = $this->userModel()::factory();

        if (! $verified) {
            $factory = $factory->unverified();
        }

        return $factory->create(array_merge(['role_id' => 3], $attributes));
    }

    protected function verknuepfeOidc(Model $user, string $sub): void
    {
        DB::table('user_social_accounts')->insert([
            'user_id' => $user->getKey(),
            'provider' => 'oidc',
            'provider_user_id' => $sub,
            'email' => $user->email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function oidcSitzungStarten(): void
    {
        $this->sitzungsId = bin2hex(random_bytes(20));
        $this->withCookie(config('session.cookie'), $this->sitzungsId);
    }

    /**
     * Echter Redirect-Aufruf; liefert state und nonce, wie sie in der Sitzung liegen.
     *
     * @return array{0: string, 1: string}
     */
    protected function oidcStart(): array
    {
        $this->get(route('userauth.oidc.redirect'))->assertRedirect();

        $state = session('userauth.oidc.state');
        $nonce = session('userauth.oidc.nonce');
        $this->assertNotEmpty($state, 'Der Redirect legt keinen state in der Sitzung ab.');
        $this->assertNotEmpty($nonce, 'Der Redirect legt keine nonce in der Sitzung ab.');

        return [$state, $nonce];
    }

    /**
     * @param  array<string, string>  $query
     */
    protected function oidcCallback(array $query): TestResponse
    {
        return $this->get(route('userauth.oidc.callback', $query));
    }

    protected function loginLokalUrl(): string
    {
        return route('login', ['lokal' => 1]);
    }

    /**
     * Keine Anmeldung, Weiterleitung auf die Login-Seite mit lokalem Formular und allgemeiner Fehlermeldung.
     */
    protected function assertAbgelehnt(TestResponse $antwort): void
    {
        $antwort->assertRedirect($this->loginLokalUrl());
        $antwort->assertSessionHasErrors();
        $this->assertGuest();
    }

    /**
     * @return list<string> alle Fehlermeldungen der Sitzung
     */
    protected function fehlermeldungen(): array
    {
        $fehler = session('errors');

        return $fehler === null ? [] : array_map('strval', $fehler->all());
    }

    protected function logSammeln(): void
    {
        $this->logTexte = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e): void {
            $this->logTexte[] = $e->message.' '.json_encode($e->context, JSON_PARTIAL_OUTPUT_ON_ERROR);
        });
    }
}
