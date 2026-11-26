<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Oidc;

/**
 * Geprüfte Identität vom OIDC-Anbieter: `sub` als Kennung, bestätigte E-Mail, Anzeigename, Gruppen.
 */
final readonly class OidcIdentity
{
    /**
     * @param  list<string>  $groups
     */
    public function __construct(
        public string $subject,
        public string $email,
        public ?string $name,
        public array $groups,
    ) {}
}
