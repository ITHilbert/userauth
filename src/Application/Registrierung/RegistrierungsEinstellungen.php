<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Registrierung;

use Illuminate\Contracts\Config\Repository;

/**
 * Liest `userauth.registration` zur Laufzeit. Hosts setzen die Gruppe oft als Ganzes (die Paket-Config wird
 * flach gemergt) – fehlende Schlüssel bekommen hier ihre Vorgabe.
 */
final class RegistrierungsEinstellungen
{
    private const STANDARD_MINUTEN = 60;

    public function __construct(private readonly Repository $config) {}

    public function aktiv(): bool
    {
        return (bool) $this->config->get('userauth.registration.enabled', false);
    }

    /**
     * Rollen-Schlüssel (`roles.role`) für neue Konten; null = keine Rolle.
     */
    public function rollenSchluessel(): ?string
    {
        $schluessel = $this->config->get('userauth.registration.role');

        if (! is_string($schluessel) || trim($schluessel) === '') {
            return null;
        }

        return trim($schluessel);
    }

    /**
     * Gültigkeit des Bestätigungslinks; leer, keine Zahl oder < 1 → 60 Minuten.
     */
    public function gueltigMinuten(): int
    {
        $minuten = $this->config->get('userauth.registration.verify_ttl_minutes');

        if (! is_numeric($minuten) || (int) $minuten < 1) {
            return self::STANDARD_MINUTEN;
        }

        return (int) $minuten;
    }
}
