<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\ViewComposers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Die Verwaltungsseiten sind für das Blade-Theme `kern` des Kits gebaut (Klassen `kit-…`, CSS des Pakets).
 * Steht `laravelkit.theme` auf etwas anderem, rendert die Seite trotzdem; userauth schreibt dann je Request
 * eine Warnung ins Log, damit der Host die Einstellung nachzieht.
 */
final class VerwaltungThemeHinweisComposer
{
    public const THEME = 'kern';

    /** Standard des Kits, wenn `laravelkit.theme` nicht gesetzt ist. */
    private const KIT_STANDARD = 'tailwind';

    private const GEMELDET = 'userauth.verwaltung_theme_gemeldet';

    public function __construct(private readonly Request $request) {}

    public function compose(View $view): void
    {
        $theme = config('laravelkit.theme', self::KIT_STANDARD);
        if ($theme === self::THEME || $this->request->attributes->get(self::GEMELDET) === true) {
            return;
        }

        $this->request->attributes->set(self::GEMELDET, true);
        Log::warning('userauth: Verwaltungsseiten brauchen laravelkit.theme=kern (LARAVELKIT_THEME=kern), aktuell: '.(is_string($theme) ? $theme : get_debug_type($theme)));
    }
}
