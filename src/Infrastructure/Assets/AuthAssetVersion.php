<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Assets;

/**
 * Liefert Pfad und Cache-Busting-Version der vorkompilierten Package-CSS.
 */
final class AuthAssetVersion
{
    private ?string $cssVersion = null;

    public function cssPath(): string
    {
        return dirname(__DIR__, 2).'/Resources/assets/css/userauth.css';
    }

    public function cssVersion(): string
    {
        if ($this->cssVersion === null) {
            $hash = is_file($this->cssPath()) ? hash_file('sha256', $this->cssPath()) : false;
            $this->cssVersion = $hash === false ? '0' : substr($hash, 0, 12);
        }

        return $this->cssVersion;
    }
}
