<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Splitstack\Rome\Exceptions\RomeConfigurationException;
use Splitstack\Rome\Jobs\RefreshMaterializedView;

it('throws RomeConfigurationException when no connection is configured and none is set on the job', function () {
    config()->set('rome.db_connections', []);

    (new RefreshMaterializedView(viewName: 'my_view'))->handle();
})->throws(RomeConfigurationException::class, 'No connection specified');

it('resolves "default" to database.default connection', function () {
    config()->set('database.default', 'testing');
    config()->set('rome.db_connections', ['default']);

    $mockLock = Mockery::mock();
    $mockLock->shouldReceive('get')->once()->andReturn(false);

    Cache::shouldReceive('lock')
        ->once()
        ->with('refresh_mat_view__my_view', 300)
        ->andReturn($mockLock);

    (new RefreshMaterializedView(viewName: 'my_view'))->handle();
});

it('skips refresh and logs when the lock is already held by another process', function () {
    $mockLock = Mockery::mock();
    $mockLock->shouldReceive('get')->once()->andReturn(false);

    Cache::shouldReceive('lock')
        ->once()
        ->with('refresh_mat_view__my_view', 300)
        ->andReturn($mockLock);

    Log::spy();

    (new RefreshMaterializedView(viewName: 'my_view', dbConnection: 'testing'))->handle();

    Log::shouldHaveReceived('info')
        ->with(
            'Skipping materialized view refresh — another refresh is already in progress',
            Mockery::subset(['view_name' => 'my_view'])
        );
});

it('calls all onFailure callbacks when the job permanently fails', function () {
    $results = [];

    $job = new RefreshMaterializedView(
        viewName: 'my_view',
        dbConnection: 'testing',
        onFailure: [
            function (\Throwable $e) use (&$results) {
                $results[] = 'first: '.$e->getMessage();
            },
            function (\Throwable $e, RefreshMaterializedView $job) use (&$results) {
                $results[] = 'second: '.$job->viewName;
            },
        ],
    );

    $job->failed(new \RuntimeException('disk full'));

    expect($results)->toBe(['first: disk full', 'second: my_view']);
});
