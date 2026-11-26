<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

/**
 * Die Anwendung als Gegenstelle der Passkeys (Relying Party): Domain, angezeigter Name, erlaubte Ursprünge.
 */
final readonly class PasskeyPartei
{
    /**
     * @param  list<string>  $urspruenge
     */
    public function __construct(
        public string $rpId,
        public string $name,
        public array $urspruenge,
    ) {}
}
