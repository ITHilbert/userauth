<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests\Feature;

use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\Tests\TestCase;

/**
 * Standardbild bei Benutzern ohne Bild (userauth-bildpfad, Dashboard #358).
 * Abgedeckt: AK1, AK2 und der Randfall „nur Leerzeichen“.
 */
final class BildpfadTest extends TestCase
{
    private const STANDARDBILD = 'vendor/userauth/img/default-user.jpg';

    /**
     * Führt den Aufruf aus und sammelt alle Deprecation-Meldungen.
     *
     * @return array{0: mixed, 1: list<string>}
     */
    private function mitDeprecations(callable $aufruf): array
    {
        $meldungen = [];
        set_error_handler(function (int $nr, string $text) use (&$meldungen): bool {
            $meldungen[] = $text;

            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            $ergebnis = $aufruf();
        } finally {
            restore_error_handler();
        }

        return [$ergebnis, $meldungen];
    }

    /** Gegenprobe: Der Mechanismus erkennt die Deprecation des alten Fehlers (`asset(null)`). */
    private function pruefeMechanismus(): void
    {
        [, $meldungen] = $this->mitDeprecations(fn () => asset(null));
        $this->assertNotEmpty($meldungen, 'Der Fang-Mechanismus erkennt keine Deprecation bei asset(null).');
    }

    private function benutzerMitBild(?string $bild): User
    {
        $benutzer = new User;
        $benutzer->setAttribute('image', $bild);

        return $benutzer;
    }

    private function pruefeStandardbild(?string $bild): void
    {
        $this->pruefeMechanismus();

        [$pfad] = $this->mitDeprecations(fn () => $this->benutzerMitBild($bild)->getImagePath());

        $this->assertSame(asset(self::STANDARDBILD), $pfad);
    }

    private function pruefeOhneDeprecation(?string $bild): void
    {
        $this->pruefeMechanismus();

        [, $meldungen] = $this->mitDeprecations(fn () => $this->benutzerMitBild($bild)->getImagePath());

        $this->assertSame([], $meldungen);
    }

    // AK1
    public function test_liefert_standardbild_wenn_image_null_ist(): void
    {
        $this->pruefeStandardbild(null);
    }

    // AK1
    public function test_liefert_standardbild_wenn_image_leer_ist(): void
    {
        $this->pruefeStandardbild('');
    }

    // AK1
    public function test_gibt_keine_deprecation_aus_wenn_image_null_ist(): void
    {
        $this->pruefeOhneDeprecation(null);
    }

    // AK1
    public function test_gibt_keine_deprecation_aus_wenn_image_leer_ist(): void
    {
        $this->pruefeOhneDeprecation('');
    }

    // AK2
    public function test_liefert_eigenes_bild_wie_bisher(): void
    {
        [$pfad, $meldungen] = $this->mitDeprecations(
            fn () => $this->benutzerMitBild('storage/users/x.jpg')->getImagePath()
        );

        $this->assertSame(asset('storage/users/x.jpg'), $pfad);
        $this->assertNotSame(asset(self::STANDARDBILD), $pfad);
        $this->assertSame([], $meldungen);
    }

    // Randfall 1
    public function test_liefert_standardbild_wenn_image_nur_leerzeichen_enthaelt(): void
    {
        $this->pruefeStandardbild(' ');
        $this->pruefeStandardbild("   \t ");
    }

    // Randfall 1
    public function test_gibt_keine_deprecation_aus_wenn_image_nur_leerzeichen_enthaelt(): void
    {
        $this->pruefeOhneDeprecation('   ');
    }
}
