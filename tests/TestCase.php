<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Tests;

use Illuminate\Foundation\Application;
use ITHilbert\UserAuth\Tests\Fixtures\User;
use ITHilbert\UserAuth\UserAuthServiceProvider;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Yajra\DataTables\DataTablesServiceProvider;

/**
 * Basis aller Paket-Tests: eigene Testbench-App, unabhängig vom Host.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            UserAuthServiceProvider::class,
            SocialiteServiceProvider::class,
            DataTablesServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('mail.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('hashing.bcrypt.rounds', 4);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Laravel-Standardtabellen (password_reset_tokens, sessions, …). Die Paket-Migration
        // 2019_10_12_000000 legt `users` danach selbst neu an.
        $this->loadLaravelMigrations();
    }
}
