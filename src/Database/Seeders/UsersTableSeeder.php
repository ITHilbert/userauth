<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Legt ein Admin-Konto nur an, wenn die Tabelle users komplett leer ist (auch ohne soft-gelöschte
 * Zeilen) und die App unter einer .lan-Domain läuft. Vorhandene Benutzer werden nie angetastet.
 */
class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('users')->exists()) {
            return;
        }

        if (! $this->istHeimnetz()) {
            $this->meldeKeinKonto('keine .lan-Domain in app.url');

            return;
        }

        $rolleId = DB::table('roles')->where('role', 'dev')->value('id');

        if ($rolleId === null) {
            $this->meldeKeinKonto('Rolle dev fehlt');

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

        return is_string($host) && str_ends_with(strtolower($host), '.lan');
    }

    private function meldeKeinKonto(string $grund): void
    {
        $this->command?->warn('Kein Admin-Konto angelegt: '.$grund.' (Konto nur unter einer .lan-Domain, Rolle dev vorausgesetzt).');
    }
}
