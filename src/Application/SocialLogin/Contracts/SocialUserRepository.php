<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin\Contracts;

use ITHilbert\UserAuth\Application\SocialLogin\LocalUser;

interface SocialUserRepository
{
    public function findActiveById(int|string $id): ?LocalUser;

    /**
     * Case-insensitive, ohne gelöschte Nutzer.
     */
    public function findActiveByEmail(string $email): ?LocalUser;

    public function existsDeletedWithEmail(string $email): bool;

    public function createVerified(?string $name, string $email, string $roleKey): LocalUser;

    /**
     * Setzt die E-Mail als bestätigt, falls noch offen. Ein Konto mit offener Bestätigung aus der
     * Selbstregistrierung verliert dabei sein Passwort und seine Angemeldet-bleiben-Tokens (Pre-Account-Takeover).
     */
    public function markEmailVerified(int|string $id): void;
}
