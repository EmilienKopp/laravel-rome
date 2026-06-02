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

    protected static $proxiedModelClass = RomeTest_ConcreteModel::class;

    protected $fillable = ['name', 'status'];

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

it('throws ProxiedModelException on update when proxied class is not defined', function () {
    $model = new RomeTest_ViewWithoutProxy;
    $model->id = 1;
    $model->update(['name' => 'changed']);
})->throws(ProxiedModelException::class, 'Proxied model class not defined');

it('throws ProxiedModelException on update when proxied class does not exist', function () {
    $model = new class extends ReadOnlyModel {
        protected static $proxiedModelClass = 'App\Models\DoesNotExist';

        protected $table = 'some_view';

        public $timestamps = false;
    };
    $model->update(['name' => 'changed']);
})->throws(ProxiedModelException::class, "does not exist");

// ---------------------------------------------------------------------------
// underlying() — hydration without a DB query
// ---------------------------------------------------------------------------

it('underlying() hydrates a proxied model instance from fillable attributes', function () {
    $view = new RomeTest_ViewWithProxy([
        'id' => 42,
        'name' => 'Alice',
        'status' => 'active',
    ]);
    $view->exists = true;

    $underlying = $view->underlying();

    expect($underlying)->toBeInstanceOf(RomeTest_ConcreteModel::class)
        ->and($underlying->name)->toBe('Alice')
        ->and($underlying->status)->toBe('active')
        ->and($underlying->exists)->toBeTrue()
        ->and($underlying->wasRecentlyCreated)->toBeFalse();
});

// ---------------------------------------------------------------------------
// update() — requires a real DB to reach the "record not found" branch
// ---------------------------------------------------------------------------

describe('update() with a real DB', function () {
    beforeEach(function () {
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
