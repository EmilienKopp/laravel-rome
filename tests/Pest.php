<?php

uses(Splitstack\Rome\Tests\TestCase::class)->in('Feature');

uses(Splitstack\Rome\Tests\Integration\Postgres\PostgresIntegrationTestCase::class)
    ->in('Integration/Postgres');

uses(Splitstack\Rome\Tests\Integration\Mysql\MysqlIntegrationTestCase::class)
    ->in('Integration/Mysql');
