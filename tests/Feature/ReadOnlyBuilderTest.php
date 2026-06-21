<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Splitstack\Rome\Concerns\HasReadOnlyMode;
use Splitstack\Rome\Exceptions\ReadOnlyModelException;
use Splitstack\Rome\Models\ReadOnlyProxy;

// ---------------------------------------------------------------------------
// Fixture model
// ---------------------------------------------------------------------------

class ReadOnlyBuilder_Item extends Model
{
    use HasReadOnlyMode;

    protected $table = 'rome_builder_items';

    protected $fillable = ['name', 'status'];

    public $timestamps = false;
}

// ---------------------------------------------------------------------------
// Write-guard tests — throw before any SQL, no table required
// ---------------------------------------------------------------------------

it('update() throws ReadOnlyModelException', function () {
    ReadOnlyBuilder_Item::fromView()->update(['name' => 'x']);
})->throws(ReadOnlyModelException::class, 'Cannot bulk update via a read-only view query.');

it('delete() throws ReadOnlyModelException', function () {
    ReadOnlyBuilder_Item::fromView()->delete();
})->throws(ReadOnlyModelException::class, 'Cannot bulk delete via a read-only view query.');

it('create() throws ReadOnlyModelException', function () {
    ReadOnlyBuilder_Item::fromView()->create(['name' => 'x']);
})->throws(ReadOnlyModelException::class, 'Cannot create via a read-only view query.');

it('firstOrCreate() throws ReadOnlyModelException', function () {
    ReadOnlyBuilder_Item::fromView()->firstOrCreate(['name' => 'x']);
})->throws(ReadOnlyModelException::class, 'Cannot create via a read-only view query.');

// ---------------------------------------------------------------------------
// Query method tests — require a real table
// ---------------------------------------------------------------------------

describe('ReadOnlyBuilder query methods', function () {
    beforeEach(function () {
        Schema::create('rome_builder_items', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('status')->default('active');
        });
    });

    afterEach(function () {
        Schema::dropIfExists('rome_builder_items');
    });

    it('get() returns a Collection of ReadOnlyProxy instances', function () {
        DB::table('rome_builder_items')->insert([
            ['id' => 1, 'name' => 'Alice', 'status' => 'active'],
            ['id' => 2, 'name' => 'Bob', 'status' => 'inactive'],
        ]);

        $results = ReadOnlyBuilder_Item::fromView()->get();

        expect($results)->toBeInstanceOf(Collection::class)
            ->toHaveCount(2)
            ->each->toBeInstanceOf(ReadOnlyProxy::class);

        expect($results->first()->name)->toBe('Alice');
    });

    it('get() returns an empty Collection when no rows exist', function () {
        $results = ReadOnlyBuilder_Item::fromView()->get();

        expect($results)->toBeInstanceOf(Collection::class)->toBeEmpty();
    });

    it('first() returns a ReadOnlyProxy for the first matching record', function () {
        DB::table('rome_builder_items')->insert(['id' => 1, 'name' => 'Alice', 'status' => 'active']);

        $result = ReadOnlyBuilder_Item::fromView()->first();

        expect($result)->toBeInstanceOf(ReadOnlyProxy::class)
            ->and($result->name)->toBe('Alice');
    });

    it('first() returns null when no records match', function () {
        expect(ReadOnlyBuilder_Item::fromView()->first())->toBeNull();
    });

    it('sole() returns a ReadOnlyProxy when exactly one record exists', function () {
        DB::table('rome_builder_items')->insert(['id' => 1, 'name' => 'Alice', 'status' => 'active']);

        $result = ReadOnlyBuilder_Item::fromView()->sole();

        expect($result)->toBeInstanceOf(ReadOnlyProxy::class)
            ->and($result->name)->toBe('Alice');
    });

    it('find() returns a ReadOnlyProxy for an existing id', function () {
        DB::table('rome_builder_items')->insert(['id' => 5, 'name' => 'Carol', 'status' => 'active']);

        $result = ReadOnlyBuilder_Item::fromView()->find(5);

        expect($result)->toBeInstanceOf(ReadOnlyProxy::class)
            ->and($result->name)->toBe('Carol');
    });

    it('find() returns null for a missing id', function () {
        expect(ReadOnlyBuilder_Item::fromView()->find(9999))->toBeNull();
    });

    it('findOrFail() throws ModelNotFoundException for a missing id', function () {
        ReadOnlyBuilder_Item::fromView()->findOrFail(9999);
    })->throws(ModelNotFoundException::class);
});
