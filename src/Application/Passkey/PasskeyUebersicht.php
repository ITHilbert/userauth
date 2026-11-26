<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Passkey;

/**
 * Verwaltungsseite: eigene Passkeys und ob vor dem Löschen gewarnt wird (ohne Passkey kein Zugang mehr, AK11).
 */
final readonly class PasskeyUebersicht
{
    /**
     * @param  list<PasskeyEintrag>  $passkeys
     */
    public function __construct(
        public array $passkeys,
        public bool $warnung,
    ) {}
}
