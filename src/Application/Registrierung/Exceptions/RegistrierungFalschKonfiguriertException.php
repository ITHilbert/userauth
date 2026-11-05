<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Registrierung\Exceptions;

use RuntimeException;

/**
 * `userauth.registration.role` nennt eine Rolle, die es nicht gibt, oder eine gesperrte (admin, privilegiert; dev, früher privilegiert).
 */
final class RegistrierungFalschKonfiguriertException extends RuntimeException
{
    public static function unbekannteRolle(string $schluessel): self
    {
        return new self("Rolle '{$schluessel}' (userauth.registration.role) existiert nicht; es wurde kein Konto angelegt.");
    }

    public static function privilegierteRolle(string $schluessel): self
    {
        return new self("Rolle '{$schluessel}' (userauth.registration.role) ist gesperrt (admin bzw. dev) und darf nicht an selbst registrierte Konten gehen; es wurde kein Konto angelegt.");
    }
}
