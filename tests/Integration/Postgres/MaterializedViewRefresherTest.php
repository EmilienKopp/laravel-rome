<?php

use Illuminate\Support\Facades\Log;
use Splitstack\Rome\Database\MaterializedViewRefresher;
use Splitstack\Rome\Database\ViewDialect;
use Splitstack\Rome\Tests\Integration\Fixtures\Models\IntegrationMaterializedViewModel;
use Splitstack\Rome\Tests\Integration\Support\SchemaHelper;

beforeEach(function () {
    $this->helper = new SchemaHelper('pgsql_test');
    $this->helper->createItemsTableAndView();
});

afterEach(function () {
    $this->helper?->dropMaterializedView();
    $this->helper?->dropItemsTableAndView();
});

it('queries a materialized view via ReadOnlyModel', function () {
    $this->helper->seedItem(1, 'Alice', 'active');
    $this->helper->createMaterializedView();

    $model = IntegrationMaterializedViewModel::find(1);

    expect($model)->not->toBeNull()
        ->and($model->name)->toBe('Alice')
        ->and($model->display_name)->toBe('Alice [active]');
});

it('refresh() updates the materialized view after new rows are inserted', function () {
    $this->helper->seedItem(1, 'Alice');
    $this->helper->createMaterializedView();

    $this->helper->seedItem(2, 'Bob');

    expect(IntegrationMaterializedViewModel::count())->toBe(1);

    (new MaterializedViewRefresher('pgsql_test'))
        ->refresh('rome_integration_items_matview');

    expect(IntegrationMaterializedViewModel::count())->toBe(2);
});

it('concurrent refresh succeeds when a unique index exists', function () {
    $this->helper->seedItem(1, 'Alice');
    $this->helper->createMaterializedView();
    $this->helper->addUniqueIndexToMatview();

    (new MaterializedViewRefresher('pgsql_test'))
        ->refresh('rome_integration_items_matview', concurrent: true);

    expect(IntegrationMaterializedViewModel::count())->toBe(1);
});

it('falls back to blocking refresh and logs a warning when no unique index exists', function () {
    $this->helper->seedItem(1, 'Alice');
    $this->helper->createMaterializedView();

    Log::spy();

    (new MaterializedViewRefresher('pgsql_test'))
        ->refresh('rome_integration_items_matview', concurrent: true);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($msg) => str_contains($msg, 'no unique index'));
});

it('hasUniqueIndex() returns true after creating the unique index', function () {
    $this->helper->seedItem(1, 'Alice');
    $this->helper->createMaterializedView();
    $this->helper->addUniqueIndexToMatview();

    expect(
        (new MaterializedViewRefresher('pgsql_test'))->hasUniqueIndex('rome_integration_items_matview')
    )->toBeTrue();
});

it('hasUniqueIndex() returns false when no unique index exists', function () {
    $this->helper->seedItem(1, 'Alice');
    $this->helper->createMaterializedView();

    expect(
        (new MaterializedViewRefresher('pgsql_test'))->hasUniqueIndex('rome_integration_items_matview')
    )->toBeFalse();
});

it('ViewDialect::fromConnection resolves pgsql driver and supports materialized views', function () {
    $dialect = ViewDialect::fromConnection('pgsql_test');

    expect($dialect->driver())->toBe('pgsql')
        ->and($dialect->supportsMaterializedViews())->toBeTrue();
});
