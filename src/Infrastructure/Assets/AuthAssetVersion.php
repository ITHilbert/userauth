<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Assets;

/**
 * Liefert Pfad und Cache-Busting-Version der vorkompilierten Package-CSS
 * (`userauth.css` der Auth-Seiten, `userauth-verwaltung.css` der Verwaltungsseiten).
 */
final class AuthAssetVersion
{
    private ?string $cssVersion = null;

    private ?string $verwaltungCssVersion = null;

    public function cssPath(): string
    {
        return dirname(__DIR__, 2).'/Resources/assets/css/userauth.css';
    }

    public function cssVersion(): string
    {
        return $this->cssVersion ??= $this->version($this->cssPath());
    }

    public function verwaltungCssPath(): string
    {
        return dirname(__DIR__, 2).'/Resources/assets/css/userauth-verwaltung.css';
    }

    public function verwaltungCssVersion(): string
    {
        return $this->verwaltungCssVersion ??= $this->version($this->verwaltungCssPath());
    }

    /** 12-stelliger Hash des Inhalts, `0`, wenn die Datei fehlt. */
    private function version(string $pfad): string
    {
        $hash = is_file($pfad) ? hash_file('sha256', $pfad) : false;

        return $hash === false ? '0' : substr($hash, 0, 12);
    }
}
