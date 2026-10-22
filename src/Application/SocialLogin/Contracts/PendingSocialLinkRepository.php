<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin\Contracts;

use DateTimeInterface;
use ITHilbert\UserAuth\Application\SocialLogin\PendingSocialLink;
use ITHilbert\UserAuth\Domain\SocialLogin\SocialIdentity;

interface PendingSocialLinkRepository
{
    /**
     * Ersetzt einen evtl. vorhandenen offenen Link des Nutzers für denselben Anbieter.
     */
    public function replace(int|string $userId, SocialIdentity $identity, string $tokenHash, DateTimeInterface $expiresAt): void;

    public function findValid(string $tokenHash, DateTimeInterface $now): ?PendingSocialLink;

    /**
     * Wie findValid, sperrt die Zeile jedoch bis Transaktionsende (nur innerhalb einer Transaktion aufrufen).
     */
    public function findValidForUpdate(string $tokenHash, DateTimeInterface $now): ?PendingSocialLink;

    public function delete(string $tokenHash): void;
}
