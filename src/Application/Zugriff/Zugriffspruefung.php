<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Zugriff;

use Illuminate\Contracts\Auth\Authenticatable;
use ITHilbert\UserAuth\Application\Zugriff\Contracts\RollenRepository;
use ITHilbert\UserAuth\Domain\Zugriff\Namensliste;
use ITHilbert\UserAuth\Domain\Zugriff\Rolle;

/**
 * Zentrale, fail-closed Rollen- und Rechteprüfung des Pakets. Gate, Middlewares,
 * Blade-Direktiven und die Methoden des Traits UserAuth fragen nur hier.
 *
 * Kein Nutzer (Gast) oder keine gültige Rolle (null, 0, negativ, nicht vorhanden,
 * gelöscht) → nie privilegiert, keine Rechte, keine Rolle.
 */
final class Zugriffspruefung
{
    public function __construct(private readonly RollenRepository $rollen) {}

    public function aktiveRolle(?Authenticatable $nutzer): ?Rolle
    {
        return $nutzer === null ? null : $this->rollen->aktiveRolle($nutzer);
    }

    /**
     * Dev (1) oder Admin (2).
     */
    public function istPrivilegiert(?Authenticatable $nutzer): bool
    {
        return $this->aktiveRolle($nutzer)?->istPrivilegiert() ?? false;
    }

    public function istDev(?Authenticatable $nutzer): bool
    {
        return $this->aktiveRolle($nutzer)?->istDev() ?? false;
    }

    public function hatRecht(?Authenticatable $nutzer, string $recht): bool
    {
        return $this->aktiveRolle($nutzer)?->hatRecht($recht) ?? false;
    }

    /**
     * @param  string|int|array<array-key, mixed>  ...$rechte  einzeln, Komma-Liste oder Array
     */
    public function hatAlleRechte(?Authenticatable $nutzer, string|int|array ...$rechte): bool
    {
        return $this->aktiveRolle($nutzer)?->hatAlleRechte(Namensliste::aus(...$rechte)) ?? false;
    }

    /**
     * @param  string|int|array<array-key, mixed>  ...$rechte  einzeln, Komma-Liste oder Array
     */
    public function hatEinesDerRechte(?Authenticatable $nutzer, string|int|array ...$rechte): bool
    {
        return $this->aktiveRolle($nutzer)?->hatEinesDerRechte(Namensliste::aus(...$rechte)) ?? false;
    }

    /**
     * Dev erfüllt jede Rolle, Admin jede außer `dev`, sonst nur der eigene Rollenname.
     */
    public function hatRolle(?Authenticatable $nutzer, string $rolle): bool
    {
        return $this->aktiveRolle($nutzer)?->erfuelltRolle($rolle) ?? false;
    }

    /**
     * Rollenname ist einer der genannten (ohne Sonderstellung für Dev/Admin).
     *
     * @param  string|int|array<array-key, mixed>  ...$rollen  einzeln, Komma-Liste oder Array
     */
    public function hatEineDerRollen(?Authenticatable $nutzer, string|int|array ...$rollen): bool
    {
        return $this->aktiveRolle($nutzer)?->traegtEinenDerNamen(Namensliste::aus(...$rollen)) ?? false;
    }
}
