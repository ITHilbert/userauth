<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Contracts;

use DateTimeInterface;

/**
 * Erzeugt Links für Mails. Basis ist die konfigurierte öffentliche Adresse (`app.url`), nicht die Anfrage:
 * Host und Schema der Anfrage sind über Proxy-Header manipulierbar (Host-Header-Poisoning).
 */
interface MailLinks
{
    /**
     * @param  array<string, mixed>|string  $parameter
     */
    public function fuerRoute(string $routenName, array|string $parameter = []): string;

    /**
     * Signierter, bis `$ablauf` gültiger Link. Die Signatur gilt für die absolute URL aus `app.url`.
     *
     * @param  array<string, mixed>  $parameter
     */
    public function signiertFuerRoute(string $routenName, array $parameter, DateTimeInterface $ablauf): string;
}
