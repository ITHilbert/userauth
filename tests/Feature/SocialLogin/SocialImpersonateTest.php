<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature\SocialLogin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\TestResponse;

/**
 * Social-Verknüpfungen unter Impersonate (userauth passkey-bestaetigung, #2225, AK10): anlegen und lösen sind gesperrt.
 */
final class SocialImpersonateTest extends SocialLoginTestCase
{
    /** Admin übernimmt `$ziel` über die echte Route. */
    private function uebernehmen(Model $admin, Model $ziel): void
    {
        $this->actingAs($admin)->post(route('userauth.impersonate', $ziel->getKey()))->assertRedirect();
        $this->assertAuthenticatedAs($ziel);
        $this->assertSame($admin->getKey(), session('impersonated_by'), 'Der Aufbau greift nicht: keine Impersonate-Sitzung.');
    }

    private function beenden(Model $admin): void
    {
        $this->post(route('userauth.impersonate.leave'))->assertRedirect();
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonated_by'));
    }

    private function assertGesperrt(TestResponse $antwort): void
    {
        $antwort->assertForbidden();
        $antwort->assertSee(__('userauth::passkey.impersonate_gesperrt'));
    }

    // AK10
    public function test_ak10_verknuepfen_unter_impersonate_403_ohne_verknuepfung_ohne_impersonate_unveraendert(): void
    {
        $admin = $this->makeUser(['email' => 'admin@firma.de', 'role_id' => 2]);
        $ziel = $this->makeUser(['email' => 'ziel@firma.de']);
        $this->provider->returnIdentity('google-sub-1', 'ziel.privat@gmail.com');

        $this->uebernehmen($admin, $ziel);
        $this->assertGesperrt($this->get(route('userauth.social.callback', 'google')));
        $this->assertGesperrt($this->get(route('userauth.social.redirect', 'google')));
        $this->assertDatabaseCount('user_social_accounts', 0);

        // Gegenprobe ohne Impersonate: derselbe Aufruf verknüpft, der Redirect führt zum Anbieter
        $this->beenden($admin);
        $this->get(route('userauth.social.redirect', 'google'))->assertRedirect();
        $this->get(route('userauth.social.callback', 'google'))->assertRedirect(route('userauth.social.accounts'));
        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $admin->getKey(), 'provider_user_id' => 'google-sub-1']);
    }

    // AK10
    public function test_ak10_loesen_unter_impersonate_403_verknuepfung_bleibt_ohne_impersonate_unveraendert(): void
    {
        $admin = $this->makeUser(['email' => 'admin@firma.de', 'role_id' => 2]);
        $ziel = $this->makeUser(['email' => 'ziel@firma.de']);
        $this->linkGoogle($ziel, 'google-sub-ziel');

        $this->uebernehmen($admin, $ziel);
        $this->assertGesperrt($this->delete(route('userauth.social.disconnect', 'google')));
        $this->assertDatabaseHas('user_social_accounts', ['user_id' => $ziel->getKey()]);

        // Gegenprobe: außerhalb der Übernahme löst der Benutzer seine Verknüpfung wie bisher
        $this->flushSession();
        $this->actingAs($ziel)->delete(route('userauth.social.disconnect', 'google'))->assertRedirect(route('userauth.social.accounts'));
        $this->assertDatabaseMissing('user_social_accounts', ['user_id' => $ziel->getKey()]);
    }

    // AK10
    public function test_ak10_gast_kann_sich_weiter_per_social_anmelden(): void
    {
        $nutzer = $this->makeUser(['email' => 'max@firma.de']);
        $this->linkGoogle($nutzer, 'google-sub-1');
        $this->provider->returnIdentity('google-sub-1', 'max@firma.de');

        $this->socialCallback()->assertRedirect();

        $this->assertAuthenticatedAs($nutzer);
    }
}
