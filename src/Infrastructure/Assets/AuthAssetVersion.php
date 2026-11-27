<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Assets;

use ITHilbert\LaravelKit\LaravelKitServiceProvider;
use ReflectionClass;

/**
 * Liefert Pfad und Cache-Busting-Version der vorkompilierten Package-CSS
 * (`userauth.css` der Auth-Seiten, `userauth-verwaltung.css` der Verwaltungsseiten) und des Passkey-Skripts,
 * dazu die Pfade der ausgelieferten ES-Module aus laravel-kit (`js/`, Lösch-Dialog).
 */
final class AuthAssetVersion
{
    private ?string $cssVersion = null;

    private ?string $verwaltungCssVersion = null;

    private ?string $passkeyJsVersion = null;

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

    public function passkeyJsPath(): string
    {
        return dirname(__DIR__, 2).'/Resources/assets/js/passkey.js';
    }

    public function passkeyJsVersion(): string
    {
        return $this->passkeyJsVersion ??= $this->version($this->passkeyJsPath());
    }

    /** `js/loesch-dialog.js` aus laravel-kit (exportiert `initLoeschDialog`). */
    public function kitLoeschDialogJsPath(): string
    {
        return $this->kitJsPath('loesch-dialog.js');
    }

    /** `js/loeschen.js` aus laravel-kit (Zustandslogik, importiert von `loesch-dialog.js`). */
    public function kitLoeschenJsPath(): string
    {
        return $this->kitJsPath('loeschen.js');
    }

    /** Ordner `js/` von laravel-kit, ermittelt über die Datei des ServiceProviders (`src/` liegt eine Ebene darunter). */
    private function kitJsPath(string $datei): string
    {
        $provider = (string) (new ReflectionClass(LaravelKitServiceProvider::class))->getFileName();

        return dirname($provider, 2).'/js/'.$datei;
    }

    /** 12-stelliger Hash des Inhalts, `0`, wenn die Datei fehlt. */
    private function version(string $pfad): string
    {
        $hash = is_file($pfad) ? hash_file('sha256', $pfad) : false;

        return $hash === false ? '0' : substr($hash, 0, 12);
    }
}
