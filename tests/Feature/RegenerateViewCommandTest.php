<?php

use Splitstack\Rome\Console\Commands\RegenerateViewCommand;
use Splitstack\Rome\Exceptions\RomeConfigurationException;

// ---------------------------------------------------------------------------
// Input validation
// ---------------------------------------------------------------------------

it('exits with an error when the view name contains invalid characters', function () {
    $this->artisan('dbview:regen', ['name' => 'Invalid-Name!'])
        ->expectsOutputToContain('Invalid view name')
        ->assertSuccessful();
});

it('exits with an error when the view name starts with a digit', function () {
    $this->artisan('dbview:regen', ['name' => '1bad_name'])
        ->expectsOutputToContain('Invalid view name')
        ->assertSuccessful();
});

// ---------------------------------------------------------------------------
// Configuration guards (tested via reflection to isolate from DB/filesystem)
// ---------------------------------------------------------------------------

it('resolveConnections throws RomeConfigurationException when db_connections is empty', function () {
    config()->set('rome.db_connections', []);

    $command = new RegenerateViewCommand;
    $method = new ReflectionMethod($command, 'resolveConnections');

    $method->invoke($command);
})->throws(RomeConfigurationException::class, 'rome.db_connections is empty');

it('resolveConnections maps "default" to database.default connection name', function () {
    config()->set('database.default', 'pgsql');
    config()->set('rome.db_connections', ['default', 'analytics']);

    $command = new RegenerateViewCommand;
    $method = new ReflectionMethod($command, 'resolveConnections');

    expect($method->invoke($command))->toBe(['pgsql', 'analytics']);
});

it('resolveTenants throws RomeConfigurationException when tenant_model is not configured', function () {
    config()->set('rome.tenant_model', null);

    // The exception is thrown before $this->option() is called, so no I/O setup is needed.
    $method = new ReflectionMethod(RegenerateViewCommand::class, 'resolveTenants');
    $method->invoke(new RegenerateViewCommand);
})->throws(RomeConfigurationException::class, 'rome.tenant_model is not configured');

// ---------------------------------------------------------------------------
// isMaterializedView detection
// ---------------------------------------------------------------------------

it('detects a materialized view from its SQL', function () {
    $command = new RegenerateViewCommand;
    $method = new ReflectionMethod($command, 'isMaterializedView');

    expect($method->invoke($command, 'CREATE MATERIALIZED VIEW reports AS SELECT 1'))->toBeTrue()
        ->and($method->invoke($command, 'CREATE OR REPLACE MATERIALIZED VIEW reports AS SELECT 1'))->toBeTrue()
        ->and($method->invoke($command, 'CREATE OR REPLACE VIEW reports AS SELECT 1'))->toBeFalse()
        ->and($method->invoke($command, "-- materialized comment\nCREATE VIEW reports AS SELECT 1"))->toBeFalse();
});

it('extracts the target view name from SQL definition', function () {
    $command = new RegenerateViewCommand;
    $method = new ReflectionMethod($command, 'extractViewNameFromSql');

    expect($method->invoke($command, 'CREATE OR REPLACE VIEW sales_view AS SELECT 1'))->toBe('sales_view')
        ->and($method->invoke($command, 'CREATE MATERIALIZED VIEW analytics.daily_rollup AS SELECT 1'))->toBe('analytics.daily_rollup')
        ->and($method->invoke($command, 'SELECT 1'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Dry-run mode
// ---------------------------------------------------------------------------

it('dry-run shows what would be regenerated without executing any SQL', function () {
    $viewsPath = sys_get_temp_dir().'/rome_test_'.uniqid();
    mkdir($viewsPath);
    file_put_contents("{$viewsPath}/sales.sql", 'CREATE OR REPLACE VIEW sales_view AS SELECT 1');

    config()->set('rome.db_views_path', $viewsPath);
    config()->set('rome.db_connections', ['testing']);

    $this->artisan('dbview:regen', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN')
        ->expectsOutputToContain('sales')
        ->assertSuccessful();

    // cleanup
    unlink("{$viewsPath}/sales.sql");
    rmdir($viewsPath);
});

it('dry-run labels materialized views correctly', function () {
    $viewsPath = sys_get_temp_dir().'/rome_test_'.uniqid();
    mkdir($viewsPath);
    file_put_contents("{$viewsPath}/metrics.sql", 'CREATE MATERIALIZED VIEW metrics_view AS SELECT 1');

    config()->set('rome.db_views_path', $viewsPath);
    config()->set('rome.db_connections', ['testing']);

    $this->artisan('dbview:regen', ['--dry-run' => true])
        ->expectsOutputToContain('materialized view')
        ->assertSuccessful();

    unlink("{$viewsPath}/metrics.sql");
    rmdir($viewsPath);
});
