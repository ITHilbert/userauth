<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Domain\Zugriff;

use InvalidArgumentException;

/**
 * Liste von Rechte- oder Rollennamen, egal in welcher Schreibweise sie ankommt:
 * einzeln (Middleware `hasPermissionAnd:a,b` → 'a', 'b'), als Komma-Liste ('a, b')
 * oder als Array (['a', 'b']). Leere Einträge entfallen, Leerzeichen werden entfernt.
 */
final readonly class Namensliste
{
    /**
     * @param  list<string>  $namen
     * @param  bool  $hatFehlendenEintrag  mindestens ein Eintrag war null (gilt als nicht vorhandenes Recht)
     */
    private function __construct(public array $namen, public bool $hatFehlendenEintrag = false) {}

    /**
     * @param  mixed  ...$eintraege  Text, Zahl, Array oder null (null = fehlender Eintrag)
     *
     * @throws InvalidArgumentException wenn ein Eintrag weder Text, Zahl noch null ist
     */
    public static function aus(mixed ...$eintraege): self
    {
        $namen = [];
        $fehlend = false;

        array_walk_recursive($eintraege, static function (mixed $eintrag) use (&$namen, &$fehlend): void {
            if ($eintrag === null) {
                $fehlend = true;

                return;
            }

            if (! is_string($eintrag) && ! is_int($eintrag)) {
                throw new InvalidArgumentException('Rechte- und Rollennamen müssen Text sein, erhalten: '.get_debug_type($eintrag));
            }

            foreach (explode(',', (string) $eintrag) as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $namen[] = $name;
                }
            }
        });

        return new self(array_values(array_unique($namen)), $fehlend);
    }

    public function istLeer(): bool
    {
        return $this->namen === [];
    }

    public function enthaelt(string $name): bool
    {
        return in_array($name, $this->namen, true);
    }
}
