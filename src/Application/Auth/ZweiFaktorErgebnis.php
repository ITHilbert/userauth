<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Auth;

final readonly class ZweiFaktorErgebnis
{
    /**
     * @param  ?string  $ziel  Ursprünglich gewünschte Seite (nur bei Bestanden)
     */
    private function __construct(
        public ZweiFaktorStatus $status,
        public ?string $ziel = null,
    ) {}

    public static function bestanden(?string $ziel): self
    {
        return new self(ZweiFaktorStatus::Bestanden, $ziel);
    }

    public static function mit(ZweiFaktorStatus $status): self
    {
        return new self($status);
    }
}
