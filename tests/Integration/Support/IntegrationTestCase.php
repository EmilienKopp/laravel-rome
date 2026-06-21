<?php

namespace Splitstack\Rome\Tests\Integration\Support;

use Illuminate\Support\Facades\DB;
use Splitstack\Rome\Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected string $connectionName = 'pgsql_test';

    public function getEnvironmentSetUp($app): void
    {
        config()->set('rome.proxy_enabled', true);

        config()->set('database.connections.pgsql_test', [
            'driver'   => 'pgsql',
            'host'     => env('PGSQL_HOST', '127.0.0.1'),
            'port'     => env('PGSQL_PORT', '5432'),
            'database' => env('PGSQL_DB', 'rome_test'),
            'username' => env('PGSQL_USER', 'rome_test'),
            'password' => env('PGSQL_PASSWORD', 'secret'),
            'charset'  => 'utf8',
            'schema'   => 'public',
        ]);

        config()->set('database.connections.mysql_test', [
            'driver'     => 'mysql',
            'host'       => env('MYSQL_HOST', '127.0.0.1'),
            'port'       => env('MYSQL_PORT', '3306'),
            'database'   => env('MYSQL_DB', 'rome_test'),
            'username'   => env('MYSQL_USER', 'rome_test'),
            'password'   => env('MYSQL_PASSWORD', 'secret'),
            'charset'    => 'utf8mb4',
            'collation'  => 'utf8mb4_unicode_ci',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfConnectionUnavailable($this->connectionName);
    }

    protected function skipIfConnectionUnavailable(string $connection): void
    {
        try {
            DB::connection($connection)->getPdo();
        } catch (\Exception $e) {
            $this->markTestSkipped(
                "Integration tests skipped: cannot connect to '{$connection}'. "
                ."Start containers with 'docker compose up -d' and set PGSQL_*/MYSQL_* env vars. "
                ."Original error: ".$e->getMessage()
            );
        }
    }
}
