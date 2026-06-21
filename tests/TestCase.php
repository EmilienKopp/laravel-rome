<?php

namespace Splitstack\Rome\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Splitstack\Rome\RomeServiceProvider;
use Splitstack\Rome\Tests\Integration\Support\SchemaHelper;

class TestCase extends Orchestra
{
    public ?SchemaHelper $helper = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            RomeServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
