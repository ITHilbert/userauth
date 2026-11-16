<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passwort\Contracts;

/**
 * Reset-Tokens je Konto (Modus `benutzername`/`beides`): Konten dürfen eine E-Mail teilen,
 * deshalb ist der Schlüssel des Tokens das Konto, nicht die E-Mail.
 */
interface ResetTokens
{
    /**
     * Neues Token (ersetzt ein älteres des Kontos); null, wenn kurz zuvor schon eines ausgestellt wurde.
     */
    public function ausstellen(int|string $kontoId): ?string;

    /**
     * Gehört das Token zum Konto und ist es nicht abgelaufen?
     */
    public function gueltig(int|string $kontoId, #[\SensitiveParameter] string $token): bool;

    public function verbrauchen(int|string $kontoId): void;
}
