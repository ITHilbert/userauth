<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Http\Controllers;

use ITHilbert\UserAuth\Infrastructure\Assets\AuthAssetVersion;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert die vorkompilierten CSS-Dateien und das Passkey-Skript des Pakets aus (öffentlich, ohne Sitzung,
 * versioniert über ?v=), dazu die zwei ES-Module des Lösch-Dialogs aus laravel-kit (feste Whitelist, kurzer Cache).
 */
final class AuthCssController
{
    private const CSS = 'text/css; charset=utf-8';

    private const JS = 'text/javascript; charset=utf-8';

    /** Für Adressen mit ?v=<Inhalts-Hash>. */
    private const CACHE_VERSIONIERT = 'public, max-age=31536000, immutable';

    /** Für die Kit-Module: der Browser lädt den relativen Import `./loeschen.js` ohne Versionsparameter. */
    private const CACHE_KURZ = 'public, max-age=300';

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

    /** `passkey.js` für Login-Knopf und Verwaltungsseite (reines JavaScript, ohne Build). */
    public function passkey(AuthAssetVersion $assets): Response
    {
        return $this->datei($assets->passkeyJsPath(), self::JS);
    }

    /** `loesch-dialog.js` aus laravel-kit (`initLoeschDialog`). */
    public function kitLoeschDialog(AuthAssetVersion $assets): Response
    {
        return $this->datei($assets->kitLoeschDialogJsPath(), self::JS, self::CACHE_KURZ);
    }

    /** `loeschen.js` aus laravel-kit, relativ importiert von `loesch-dialog.js`. */
    public function kitLoeschen(AuthAssetVersion $assets): Response
    {
        return $this->datei($assets->kitLoeschenJsPath(), self::JS, self::CACHE_KURZ);
    }

    private function datei(string $pfad, string $typ = self::CSS, string $cache = self::CACHE_VERSIONIERT): Response
    {
        abort_unless(is_file($pfad), 404);

        return response()->file($pfad, [
            'Content-Type' => $typ,
            'Cache-Control' => $cache,
        ]);
    }
}
