<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use ITHilbert\UserAuth\Infrastructure\Assets\AuthAssetVersion;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert die vorkompilierten CSS-Dateien des Pakets aus (öffentlich, versioniert über ?v=).
 */
final class AuthCssController
{
    /** `userauth.css` der Auth-Seiten (mit Preflight). */
    public function __invoke(AuthAssetVersion $assets): Response
    {
        return $this->datei($assets->cssPath());
    }

    /** `userauth-verwaltung.css` der Verwaltungsseiten (ohne Preflight). */
    public function verwaltung(AuthAssetVersion $assets): Response
    {
        return $this->datei($assets->verwaltungCssPath());
    }

    private function datei(string $pfad): Response
    {
        abort_unless(is_file($pfad), 404);

        return response()->file($pfad, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
