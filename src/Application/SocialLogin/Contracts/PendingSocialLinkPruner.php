<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\SocialLogin\Contracts;

use DateTimeInterface;

interface PendingSocialLinkPruner
{
    /**
     * Löscht abgelaufene offene Verknüpfungen und liefert die Anzahl der gelöschten Einträge.
     */
    public function deleteExpired(DateTimeInterface $now): int;
}
