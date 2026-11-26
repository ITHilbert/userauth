<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Oidc;

use Illuminate\Contracts\Config\Repository;

/**
 * Liest `userauth.oidc.*` erst zur Laufzeit (nicht beim Boot), damit Änderungen ohne neuen Cache wirken.
 */
final class OidcSettings
{
    public function __construct(private readonly Repository $config) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('userauth.oidc.enabled', false);
    }

    /**
     * Issuer, Client-ID und Client-Secret sind nicht leer.
     */
    public function hasCredentials(): bool
    {
        return $this->issuer() !== '' && $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function issuer(): string
    {
        return $this->text('issuer');
    }

    public function clientId(): string
    {
        return $this->text('client_id');
    }

    public function clientSecret(): string
    {
        return $this->text('client_secret');
    }

    /**
     * Pfad zum CA-Bundle für alle Aufrufe an den Anbieter; leer = Standard-Zertifikate.
     */
    public function caBundle(): string
    {
        return $this->text('ca_bundle');
    }

    public function buttonText(): string
    {
        $text = $this->text('button_text');

        return $text !== '' ? $text : (string) __('userauth::oidc.button');
    }

    public function autoCreate(): bool
    {
        return (bool) $this->config->get('userauth.oidc.auto_create', false);
    }

    public function autoRedirect(): bool
    {
        return (bool) $this->config->get('userauth.oidc.auto_redirect', true);
    }

    public function defaultRole(): string
    {
        $rolle = $this->text('default_role');

        return $rolle !== '' ? $rolle : 'user';
    }

    /**
     * Zuordnung Gruppe => Rolle in der Reihenfolge der Konfiguration (`gruppe=rolle,gruppe2=rolle2`).
     *
     * @return list<array{gruppe: string, rolle: string}>
     */
    public function groupRoles(): array
    {
        $wert = $this->config->get('userauth.oidc.group_roles', '');
        $eintraege = is_array($wert) ? $wert : explode(',', is_string($wert) ? $wert : '');
        $zuordnung = [];

        foreach ($eintraege as $eintrag) {
            if (! is_string($eintrag) || ! str_contains($eintrag, '=')) {
                continue;
            }

            [$gruppe, $rolle] = array_map(trim(...), explode('=', $eintrag, 2));

            if ($gruppe !== '' && $rolle !== '') {
                $zuordnung[] = ['gruppe' => $gruppe, 'rolle' => $rolle];
            }
        }

        return $zuordnung;
    }

    private function text(string $schluessel): string
    {
        $wert = $this->config->get("userauth.oidc.{$schluessel}");

        return is_string($wert) ? trim($wert) : '';
    }
}
