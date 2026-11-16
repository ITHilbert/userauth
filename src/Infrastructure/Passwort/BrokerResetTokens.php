<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Passwort;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use ITHilbert\UserAuth\Application\Passwort\Contracts\ResetTokens;

/**
 * Reset-Tokens je Konto im Token-Repository des Standard-Brokers (Tabelle, Hashing, Ablauf `expire`
 * und Sperre `throttle` wie im Modus `email`); Schlüssel ist das Konto (`KontoResetSchluessel`).
 */
final class BrokerResetTokens implements ResetTokens
{
    public function __construct(private readonly PasswordBrokerFactory $brokers) {}

    /**
     * @throws PasswortBrokerMisconfiguredException
     */
    public function ausstellen(int|string $kontoId): ?string
    {
        $schluessel = new KontoResetSchluessel($kontoId);
        $tokens = $this->repository();

        if ($tokens->recentlyCreatedToken($schluessel)) {
            return null;
        }

        return (string) $tokens->create($schluessel);
    }

    /**
     * @throws PasswortBrokerMisconfiguredException
     */
    public function gueltig(int|string $kontoId, #[\SensitiveParameter] string $token): bool
    {
        return (bool) $this->repository()->exists(new KontoResetSchluessel($kontoId), $token);
    }

    /**
     * @throws PasswortBrokerMisconfiguredException
     */
    public function verbrauchen(int|string $kontoId): void
    {
        $this->repository()->delete(new KontoResetSchluessel($kontoId));
    }

    /**
     * @throws PasswortBrokerMisconfiguredException
     */
    private function repository(): TokenRepositoryInterface
    {
        $broker = $this->brokers->broker();

        if (! $broker instanceof PasswordBroker) {
            throw new PasswortBrokerMisconfiguredException('The default password broker '.$broker::class.' is not '.PasswordBroker::class.'; userauth needs its token repository for per-account reset tokens.');
        }

        return $broker->getRepository();
    }
}
