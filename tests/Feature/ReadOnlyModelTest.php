<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Splitstack\Rome\Exceptions\ProxiedModelException;
use Splitstack\Rome\Exceptions\ReadOnlyModelException;
use Splitstack\Rome\Models\ReadOnlyModel;

// ---------------------------------------------------------------------------
// Fixture models
// ---------------------------------------------------------------------------

class RomeTest_ConcreteModel extends Model
{
    protected $table = 'rome_test_items';

    protected $fillable = ['name', 'status'];

    public $timestamps = false;
}

class RomeTest_ViewWithProxy extends ReadOnlyModel
{
    protected $table = 'rome_test_items_view';

    protected static $proxyTo = RomeTest_ConcreteModel::class;

    protected $fillable = ['name', 'status'];

    public $timestamps = false;
}

class RomeTest_ViewWithExclusion extends ReadOnlyModel
{
    protected $table = 'rome_test_items_view';

    protected static $proxyTo = RomeTest_ConcreteModel::class;

    protected $fillable = ['name', 'status'];

    protected static array $exclude = ['status'];

    public $timestamps = false;
}

class RomeTest_ViewWithoutProxy extends ReadOnlyModel
{
    protected $table = 'rome_test_items_view';

    public $timestamps = false;
}

// ---------------------------------------------------------------------------
// Write-guard tests (no DB required)
// ---------------------------------------------------------------------------

it('throws ReadOnlyModelException on delete', function () {
    (new RomeTest_ViewWithoutProxy)->delete();
})->throws(ReadOnlyModelException::class, 'Cannot delete from read-only model');

it('throws ReadOnlyModelException on save', function () {
    (new RomeTest_ViewWithoutProxy)->save();
})->throws(ReadOnlyModelException::class, 'Cannot save read-only model');

// ---------------------------------------------------------------------------
// Global proxy_enabled gate
// ---------------------------------------------------------------------------

it('throws when proxy_enabled is false and update() is called', function () {
    config(['rome.proxy_enabled' => false]);
    $model = new RomeTest_ViewWithProxy;
    $model->id = 1;
    $model->update(['name' => 'changed']);
})->throws(ProxiedModelException::class, 'Proxy operations are disabled globally');

it('throws when proxy_enabled is false and underlying() is called', function () {
    config(['rome.proxy_enabled' => false]);
    $model = new RomeTest_ViewWithProxy;
    $model->underlying();
})->throws(ProxiedModelException::class, 'Proxy operations are disabled globally');

it('throws when proxy_enabled is false and proxied() is called', function () {
    config(['rome.proxy_enabled' => false]);
    $model = new RomeTest_ViewWithProxy;
    $model->proxied();
})->throws(ProxiedModelException::class, 'Proxy operations are disabled globally');

// ---------------------------------------------------------------------------
// Per-model $proxyTo gate
// ---------------------------------------------------------------------------

it('throws when $proxyTo is not defined and update() is called', function () {
    config(['rome.proxy_enabled' => true]);
    $model = new RomeTest_ViewWithoutProxy;
    $model->id = 1;
    $model->update(['name' => 'changed']);
})->throws(ProxiedModelException::class, 'No proxy target defined');

it('throws when $proxyTo is not defined and underlying() is called', function () {
    config(['rome.proxy_enabled' => true]);
    (new RomeTest_ViewWithoutProxy)->underlying();
})->throws(ProxiedModelException::class, 'No proxy target defined');

it('throws when $proxyTo is not defined and proxied() is called', function () {
    config(['rome.proxy_enabled' => true]);
    (new RomeTest_ViewWithoutProxy)->proxied();
})->throws(ProxiedModelException::class, 'No proxy target defined');

it('throws when proxy target class does not exist', function () {
    config(['rome.proxy_enabled' => true]);
    $model = new class extends ReadOnlyModel
    {
        protected static $proxyTo = 'App\Models\DoesNotExist';

        protected $table = 'some_view';

        public $timestamps = false;
    };
    $model->update(['name' => 'changed']);
})->throws(ProxiedModelException::class, 'does not exist');

// ---------------------------------------------------------------------------
// underlying() and proxied() — in-memory hydration (no DB required)
// ---------------------------------------------------------------------------

it('underlying(forceFetch: false) hydrates a proxied model instance from fillable attributes', function () {
    config(['rome.proxy_enabled' => true]);

    $view = new RomeTest_ViewWithProxy(['id' => 42, 'name' => 'Alice', 'status' => 'active']);
    $view->exists = true;

    $underlying = $view->underlying(forceFetch: false);

    expect($underlying)->toBeInstanceOf(RomeTest_ConcreteModel::class)
        ->and($underlying->name)->toBe('Alice')
        ->and($underlying->status)->toBe('active')
        ->and($underlying->exists)->toBeTrue()
        ->and($underlying->wasRecentlyCreated)->toBeFalse();
});

it('proxied() is an alias for underlying(forceFetch: false)', function () {
    config(['rome.proxy_enabled' => true]);

    $view = new RomeTest_ViewWithProxy(['id' => 42, 'name' => 'Alice', 'status' => 'active']);
    $view->exists = true;

    expect($view->proxied())->toEqual($view->underlying(forceFetch: false));
});

it('$exclude strips listed attributes when hydrating via proxied()', function () {
    config(['rome.proxy_enabled' => true]);

    $view = new RomeTest_ViewWithExclusion(['id' => 1, 'name' => 'Bob', 'status' => 'active']);
    $view->exists = true;

    $proxied = $view->proxied();

    expect($proxied->name)->toBe('Bob')
        ->and($proxied->status)->toBeNull();
});

it('$exclude strips listed attributes when hydrating via underlying(forceFetch: false)', function () {
    config(['rome.proxy_enabled' => true]);

    $view = new RomeTest_ViewWithExclusion(['id' => 1, 'name' => 'Bob', 'status' => 'active']);
    $view->exists = true;

    $underlying = $view->underlying(forceFetch: false);

    expect($underlying->name)->toBe('Bob')
        ->and($underlying->status)->toBeNull();
});

// ---------------------------------------------------------------------------
// update() — requires a real DB
// ---------------------------------------------------------------------------

describe('update() with a real DB', function () {
    beforeEach(function () {
        config(['rome.proxy_enabled' => true]);

        Schema::create('rome_test_items', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('status')->default('active');
        });
    });

    afterEach(function () {
        Schema::dropIfExists('rome_test_items');
    });

    it('throws ProxiedModelException when the record does not exist in the proxied model', function () {
        $model = new RomeTest_ViewWithProxy;
        $model->id = 999;
        $model->exists = true;

        $model->update(['name' => 'changed']);
    })->throws(ProxiedModelException::class, 'Record does not exist in proxied model');

    it('underlying(forceFetch: true) queries the proxied model and returns null when not found', function () {
        $view = new RomeTest_ViewWithProxy;
        $view->id = 999;
        $view->exists = true;

        expect($view->underlying(forceFetch: true))->toBeNull();
    });
});
