<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An- und Abmeldung in der aktuellen Sitzung.
 */
interface Sitzungsanmeldung
{
    /**
     * Aktiver (nicht gelöschter) Nutzer zur ID oder null.
     */
    public function nutzer(int|string $nutzerId): ?Authenticatable;

    /**
     * Meldet an, setzt bei Bedarf das „Angemeldet bleiben“-Cookie und erneuert die Sitzungs-ID
     * (die alte Sitzung wird verworfen).
     */
    public function anmelden(Authenticatable $nutzer, bool $angemeldetBleiben): void;

    /**
     * Meldet ab (falls angemeldet) und verwirft die Sitzung samt aller Daten.
     */
    public function abmelden(): void;
}
