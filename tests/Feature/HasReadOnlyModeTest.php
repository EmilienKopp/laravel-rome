<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Splitstack\Rome\Concerns\HasReadOnlyMode;
use Splitstack\Rome\Database\ReadOnlyBuilder;
use Splitstack\Rome\Models\ReadOnlyProxy;

// ---------------------------------------------------------------------------
// Fixture models
// ---------------------------------------------------------------------------

class HasReadOnly_Item extends Model
{
    use HasReadOnlyMode;

    protected $table = 'rome_hasreadonly_items';

    protected $fillable = ['name', 'status'];

    public $timestamps = false;
}

// ---------------------------------------------------------------------------
// readonly() — no DB required
// ---------------------------------------------------------------------------

it('readonly() returns a ReadOnlyProxy wrapping the model instance', function () {
    $model = new HasReadOnly_Item(['name' => 'Alice', 'status' => 'active']);
    $proxy = $model->readonly();

    expect($proxy)->toBeInstanceOf(ReadOnlyProxy::class)
        ->and($proxy->name)->toBe('Alice');
});

it('readonly() on an already-proxied value unwraps to the base model', function () {
    $model = new HasReadOnly_Item(['name' => 'Bob']);
    $nested = new ReadOnlyProxy(new ReadOnlyProxy($model));

    expect($nested->getModel())->toBeInstanceOf(HasReadOnly_Item::class);
});

// ---------------------------------------------------------------------------
// fromView() — structural, connection only (no table required)
// ---------------------------------------------------------------------------

it('fromView() returns a ReadOnlyBuilder instance', function () {
    expect(HasReadOnly_Item::fromView())->toBeInstanceOf(ReadOnlyBuilder::class);
});

it('fromView() keeps the model table when $readOnlyView is null', function () {
    $builder = HasReadOnly_Item::fromView();

    expect($builder->getModel()->getTable())->toBe('rome_hasreadonly_items');
});

it('fromView() switches to $readOnlyView when set', function () {
    // PHP 8.2 forbids redeclaring a trait property with a different default,
    // so we set $readOnlyView via booted() on an anonymous class instead.
    $class = new class extends Model {
        use HasReadOnlyMode;

        protected $table = 'rome_hasreadonly_items';
        protected $fillable = ['name', 'status'];
        public $timestamps = false;

        protected static function booted(): void
        {
            static::$readOnlyView = 'rome_hasreadonly_items_view';
        }
    };

    $builder = $class::fromView();

    expect($builder->getModel()->getTable())->toBe('rome_hasreadonly_items_view');
});

// ---------------------------------------------------------------------------
// fromView() + real DB — query result wrapping
// ---------------------------------------------------------------------------

describe('fromView() with a real DB', function () {
    beforeEach(function () {
        Schema::create('rome_hasreadonly_items', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('status')->default('active');
        });
    });

    afterEach(function () {
        Schema::dropIfExists('rome_hasreadonly_items');
    });

    it('get() returns ReadOnlyProxy instances', function () {
        DB::table('rome_hasreadonly_items')->insert(['id' => 1, 'name' => 'Alice', 'status' => 'active']);

        $results = HasReadOnly_Item::fromView()->get();

        expect($results)->toHaveCount(1)
            ->and($results->first())->toBeInstanceOf(ReadOnlyProxy::class)
            ->and($results->first()->name)->toBe('Alice');
    });

    it('first() returns a ReadOnlyProxy for the first matching record', function () {
        DB::table('rome_hasreadonly_items')->insert(['id' => 1, 'name' => 'Alice', 'status' => 'active']);

        $result = HasReadOnly_Item::fromView()->first();

        expect($result)->toBeInstanceOf(ReadOnlyProxy::class)
            ->and($result->name)->toBe('Alice');
    });

    it('first() returns null when no records exist', function () {
        expect(HasReadOnly_Item::fromView()->first())->toBeNull();
    });

    it('find() returns a ReadOnlyProxy for an existing id', function () {
        DB::table('rome_hasreadonly_items')->insert(['id' => 7, 'name' => 'Carol', 'status' => 'active']);

        $result = HasReadOnly_Item::fromView()->find(7);

        expect($result)->toBeInstanceOf(ReadOnlyProxy::class)
            ->and($result->name)->toBe('Carol');
    });

    it('find() returns null for a missing id', function () {
        expect(HasReadOnly_Item::fromView()->find(9999))->toBeNull();
    });
});
