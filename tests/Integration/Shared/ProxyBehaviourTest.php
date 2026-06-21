<?php

namespace Splitstack\Rome\Tests\Integration\Shared;

use Illuminate\Support\Facades\DB;
use Splitstack\Rome\Exceptions\ProxiedModelException;
use Splitstack\Rome\Tests\Integration\Fixtures\Models\IntegrationConcreteModel;
use Splitstack\Rome\Tests\Integration\Fixtures\Models\IntegrationViewWithProxy;

function registerProxyBehaviourTests(): void
{
    it('underlying(forceFetch: true) queries the real backing table', function () {
        $this->helper->seedItem(1, 'Alice', 'active');
        $view = IntegrationViewWithProxy::find(1);

        $underlying = $view->underlying(forceFetch: true);

        expect($underlying)->toBeInstanceOf(IntegrationConcreteModel::class)
            ->and($underlying->name)->toBe('Alice')
            ->and($underlying->status)->toBe('active');
    });

    it('underlying(forceFetch: true) returns null when the backing row is missing', function () {
        $this->helper->seedItem(1, 'Alice');
        $view = IntegrationViewWithProxy::find(1);

        DB::connection($view->getConnectionName())
            ->table('rome_integration_items')
            ->where('id', 1)
            ->delete();

        expect($view->underlying(forceFetch: true))->toBeNull();
    });

    it('proxied() hydrates a ConcreteModel in-memory without an extra query', function () {
        $this->helper->seedItem(1, 'Alice', 'active');
        $view = IntegrationViewWithProxy::find(1);

        $proxy = $view->proxied();

        expect($proxy)->toBeInstanceOf(IntegrationConcreteModel::class)
            ->and($proxy->name)->toBe('Alice')
            ->and($proxy->status)->toBe('active')
            ->and($proxy->exists)->toBeTrue()
            ->and($proxy->wasRecentlyCreated)->toBeFalse();
    });

    it('update() writes through to the backing table', function () {
        $this->helper->seedItem(1, 'Alice', 'active');
        $view = IntegrationViewWithProxy::find(1);

        $updated = $view->update(['name' => 'Alicia']);

        expect($updated)->toBeInstanceOf(IntegrationViewWithProxy::class)
            ->and($updated->name)->toBe('Alicia')
            ->and($updated->display_name)->toBe('Alicia [active]');

        expect(IntegrationConcreteModel::find(1)->name)->toBe('Alicia');
    });

    it('update() throws ProxiedModelException when the record does not exist in the backing table', function () {
        $view = new IntegrationViewWithProxy;
        $view->id = 9999;
        $view->exists = true;

        $view->update(['name' => 'Ghost']);
    })->throws(ProxiedModelException::class, 'Record does not exist in proxied model');
}
