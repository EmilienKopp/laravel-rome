<?php

namespace Splitstack\Rome\Tests\Integration\Postgres;

use Splitstack\Rome\Tests\Integration\Support\IntegrationTestCase;

class PostgresIntegrationTestCase extends IntegrationTestCase
{
    protected string $connectionName = 'pgsql_test';

    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        config()->set('database.default', 'pgsql_test');
    }
}
