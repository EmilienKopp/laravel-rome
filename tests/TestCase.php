<?php

namespace Splitstack\Rome\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Splitstack\Rome\RomeServiceProvider;

class TestCase extends Orchestra
{
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
