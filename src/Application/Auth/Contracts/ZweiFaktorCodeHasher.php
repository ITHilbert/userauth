<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth\Contracts;

/**
 * Schlüsselgebundener Hash des Zwei-Faktor-Codes (nie Klartext ablegen, AK18) und zeitkonstanter Vergleich.
 */
interface ZweiFaktorCodeHasher
{
    /**
     * @param  string  $kennung  Bindet den Hash an genau eine offene Anmeldung
     */
    public function hash(#[\SensitiveParameter] string $code, string $kennung): string;

    public function stimmt(#[\SensitiveParameter] string $code, string $kennung, string $hash): bool;
}
