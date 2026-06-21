<?php

use Splitstack\Rome\Tests\Integration\Mysql\MysqlIntegrationTestCase;
use Splitstack\Rome\Tests\Integration\Postgres\PostgresIntegrationTestCase;
use Splitstack\Rome\Tests\TestCase;

uses(TestCase::class)->in('Feature');

uses(PostgresIntegrationTestCase::class)
    ->in('Integration/Postgres');

uses(MysqlIntegrationTestCase::class)
    ->in('Integration/Mysql');
