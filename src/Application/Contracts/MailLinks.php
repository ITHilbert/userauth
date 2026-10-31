<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Contracts;

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
}
