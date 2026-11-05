<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\ViewComposers;

use Illuminate\Contracts\View\View;

/**
 * Theme-Wahl der Einstiegs-Views an einer Stelle: `userauth.theme`, bei null/leer der Paket-Standard
 * (derselbe wie in `Config/config.php`).
 */
final class ThemeComposer
{
    public const STANDARD = 'tailwind';

    public function compose(View $view): void
    {
        $theme = config('userauth.theme');

        $view->with('userauthTheme', is_string($theme) && $theme !== '' ? $theme : self::STANDARD);
    }
}
