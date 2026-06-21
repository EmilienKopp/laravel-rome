<?php

namespace Splitstack\Rome\Tests\Integration\Mysql;

use Splitstack\Rome\Tests\Integration\Support\IntegrationTestCase;

class MysqlIntegrationTestCase extends IntegrationTestCase
{
    protected string $connectionName = 'mysql_test';

    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        config()->set('database.default', 'mysql_test');
    }
}
