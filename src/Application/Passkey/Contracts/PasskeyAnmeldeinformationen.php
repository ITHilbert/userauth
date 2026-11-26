<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey\Contracts;

use Carbon\CarbonImmutable;
use ITHilbert\UserAuth\Application\Passkey\GespeicherterPasskey;
use ITHilbert\UserAuth\Application\Passkey\NeuerPasskey;
use ITHilbert\UserAuth\Application\Passkey\PasskeyEintrag;

/**
 * Port: eingetragene Passkeys (`user_passkeys`).
 */
interface PasskeyAnmeldeinformationen
{
    public function zurKennung(string $kennung): ?GespeicherterPasskey;

    public function kennungVergeben(string $kennung): bool;

    /**
     * @return list<string> base64url-Kennungen der Passkeys des Benutzers
     */
    public function kennungenDesBenutzers(int|string $userId): array;

    /**
     * @return list<PasskeyEintrag> Passkeys des Benutzers, älteste zuerst
     */
    public function desBenutzers(int|string $userId): array;

    public function anzahl(int|string $userId): int;

    /**
     * Speichert den Passkey zum Benutzer; false, wenn die Kennung inzwischen vergeben ist.
     */
    public function speichern(int|string $userId, string $name, NeuerPasskey $passkey, CarbonImmutable $jetzt): bool;

    /**
     * Schreibt die Anmeldeinformation nach einer gelungenen Anmeldung fort (Signaturzähler, zuletzt benutzt).
     */
    public function nachAnmeldung(int $passkeyId, string $quelle, CarbonImmutable $jetzt): void;

    /**
     * Löscht den Passkey nur, wenn er dem Benutzer gehört; true, wenn gelöscht.
     */
    public function loeschen(int|string $userId, int $passkeyId): bool;

    /**
     * @return int Anzahl der gelöschten Passkeys
     */
    public function alleLoeschen(int|string $userId): int;
}
