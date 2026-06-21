<?php

namespace Splitstack\Rome\Tests\Integration\Shared;

use Splitstack\Rome\Exceptions\ReadOnlyModelException;
use Splitstack\Rome\Tests\Integration\Fixtures\Models\IntegrationViewWithProxy;

function registerReadOnlyModelBehaviourTests(): void
{
    it('finds a record via the view', function () {
        $this->helper->seedItem(1, 'Alice', 'active');

        $model = IntegrationViewWithProxy::find(1);

        expect($model)->not->toBeNull()
            ->and($model->name)->toBe('Alice')
            ->and($model->status)->toBe('active')
            ->and($model->display_name)->toBe('Alice [active]');
    });

    it('returns all seeded rows via ::all()', function () {
        $this->helper->seedItem(1, 'Alice');
        $this->helper->seedItem(2, 'Bob');
        $this->helper->seedItem(3, 'Carol');

        expect(IntegrationViewWithProxy::all())->toHaveCount(3);
    });

    it('returns null for a missing record', function () {
        expect(IntegrationViewWithProxy::find(9999))->toBeNull();
    });

    it('throws ReadOnlyModelException on delete()', function () {
        $this->helper->seedItem(1, 'Alice');
        $model = IntegrationViewWithProxy::find(1);
        $model->delete();
    })->throws(ReadOnlyModelException::class, 'Cannot delete from read-only model');

    it('throws ReadOnlyModelException on save()', function () {
        $model = new IntegrationViewWithproxied(['name' => 'new']);
        $model->save();
    })->throws(ReadOnlyModelException::class, 'Cannot save read-only model');
}
