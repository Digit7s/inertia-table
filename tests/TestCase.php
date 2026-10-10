<?php

namespace Tests;

use Digit7s\InertiaTable\InertiaTableServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            InertiaTableServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Keep SQLite in-memory as the default, but allow the same suite to run against
        // explicitly provisioned PostgreSQL/MySQL test databases in local and CI jobs.
        $driver = getenv('TEST_DB_CONNECTION');
        if ($driver) {
            config()->set('database.connections.testing', [
                'driver' => $driver,
                'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('TEST_DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306'),
                'database' => getenv('TEST_DB_DATABASE') ?: 'inertia_table_test',
                'username' => getenv('TEST_DB_USERNAME') ?: ($driver === 'pgsql' ? 'postgres' : 'root'),
                'password' => getenv('TEST_DB_PASSWORD') ?: '',
                'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => $driver === 'mysql' ? [\PDO::ATTR_EMULATE_PREPARES => true] : [],
            ]);
        }

        config()->set('app.key', 'base64:zZ6l2L7r3yL+JvP3Z3V4Q7p5mS4W3k6l2L7r3yL+JvP=');
    }
}
