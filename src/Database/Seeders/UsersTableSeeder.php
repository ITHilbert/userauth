<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ITHilbert\UserAuth\Domain\Zugriff\Rolle;

/**
 * Legt ein Admin-Konto nur an, wenn die Tabelle users komplett leer ist (auch ohne soft-gelöschte
 * Zeilen) und die App unter einer .lan-Domain oder einem lokalen Host (localhost, 127.0.0.1, ::1) läuft. Vorhandene Benutzer werden nie angetastet.
 */
class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('users')->exists()) {
            return;
        }

        if (! $this->istHeimnetz()) {
            $this->meldeKeinKonto('kein lokaler Host in app.url');

            return;
        }

        // Privilegiert über den Schlüssel, nicht über die ID (Entscheidung 0011); nur eine nicht
        // gelöschte Rolle macht das Konto privilegiert, bei mehreren die mit der kleinsten ID
        $rolleId = DB::table('roles')
            ->where('role', Rolle::NAME_ADMIN)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->value('id');

        if ($rolleId === null) {
            $this->meldeKeinKonto('Rolle admin fehlt');

            return;
        }

        $konfiguriert = config('userauth.admin_passwort');
        $zufaellig = ! is_string($konfiguriert) || $konfiguriert === '';
        $passwort = $zufaellig ? Str::password(24, symbols: false) : $konfiguriert;

        DB::table('users')->insert([
            'role_id' => $rolleId,
            'name' => 'admin',
            'email' => 'admin@admin.com',
            'email_verified_at' => null,
            'password' => Hash::make($passwort),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        $this->command?->info('Admin-Konto admin@admin.com angelegt.');

        if ($zufaellig) {
            $this->command?->line('Passwort: '.$passwort);
        }
    }

    private function istHeimnetz(): bool
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($host)) {
            return false;
        }

        $host = strtolower($host);

        return str_ends_with($host, '.lan') || in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
    }

    private function meldeKeinKonto(string $grund): void
    {
        $this->command?->warn('Kein Admin-Konto angelegt: '.$grund.' (Konto nur unter einer .lan-Domain oder einem lokalen Host, Rolle admin vorausgesetzt).');
    }
}
