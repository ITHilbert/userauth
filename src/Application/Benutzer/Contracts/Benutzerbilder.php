<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Application\Benutzer\Contracts;

use Illuminate\Http\UploadedFile;

/**
 * Bilddateien der Benutzer (Spalte `image`, Wert `storage/<pfad>` auf der Disk `public`).
 */
interface Benutzerbilder
{
    /**
     * Legt die Datei ab und liefert den Wert für die Spalte `image`; null, wenn das Ablegen scheitert.
     */
    public function ablegen(UploadedFile $datei): ?string;

    /**
     * Löscht die Datei, wenn keine Zeile in `users` (auch keine gelöschte) mehr auf sie verweist.
     * Erst nach dem Commit aufrufen. Leer oder null: nichts.
     */
    public function entfernenWennUnbenutzt(?string $bild): void;
}
