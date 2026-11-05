<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Benutzer;

use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use ITHilbert\UserAuth\Application\Benutzer\Contracts\Benutzerbilder;
use ITHilbert\UserAuth\Infrastructure\Auth\UserModel;

final class PublicDiskBenutzerbilder implements Benutzerbilder
{
    private const DISK = 'public';

    private const PRAEFIX = 'storage/';

    public function __construct(
        private readonly Filesystems $filesystems,
        private readonly UserModel $userModel,
    ) {}

    public function ablegen(UploadedFile $datei): ?string
    {
        $pfad = $this->disk()->putFile('users', $datei);

        return $pfad === false ? null : self::PRAEFIX.$pfad;
    }

    public function entfernenWennUnbenutzt(?string $bild): void
    {
        if ($bild === null || trim($bild) === '' || $this->wirdVerwendet($bild)) {
            return;
        }

        $pfad = $this->diskPfad($bild);
        if (! str_contains($pfad, '..') && $this->disk()->exists($pfad)) {
            $this->disk()->delete($pfad);
        }
    }

    /**
     * Jede Zeile zählt – auch gelöschte und solche, die ein Scope des Hosts ausblendet.
     */
    private function wirdVerwendet(string $bild): bool
    {
        return $this->userModel->query()->withoutGlobalScopes()->where('image', $bild)->exists();
    }

    /**
     * Wert der Spalte `image` als Pfad auf der Disk (Altdaten ohne Präfix `storage/` gelten relativ zur Disk).
     */
    private function diskPfad(string $bild): string
    {
        return str_starts_with($bild, self::PRAEFIX) ? substr($bild, strlen(self::PRAEFIX)) : $bild;
    }

    private function disk(): Filesystem
    {
        return $this->filesystems->disk(self::DISK);
    }
}
