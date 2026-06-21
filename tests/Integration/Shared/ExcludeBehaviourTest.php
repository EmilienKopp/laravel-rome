<?php

namespace Splitstack\Rome\Tests\Integration\Shared;

use Splitstack\Rome\Tests\Integration\Fixtures\Models\IntegrationViewWithExclusion;

function registerExcludeBehaviourTests(): void
{
    it('display_name is present on the view model itself', function () {
        $this->helper->seedItem(1, 'Carol', 'active');
        $view = IntegrationViewWithExclusion::find(1);

        expect($view->display_name)->toBe('Carol [active]');
    });

    it('$exclude strips display_name when hydrating via proxied()', function () {
        $this->helper->seedItem(1, 'Carol', 'active');
        $view = IntegrationViewWithExclusion::find(1);

        $proxy = $view->proxied();

        expect($proxy->display_name)->toBeNull()
            ->and($proxy->name)->toBe('Carol');
    });

    it('$exclude strips display_name when hydrating via underlying(forceFetch: false)', function () {
        $this->helper->seedItem(1, 'Dan', 'inactive');
        $view = IntegrationViewWithExclusion::find(1);

        $underlying = $view->underlying(forceFetch: false);

        expect($underlying->display_name)->toBeNull()
            ->and($underlying->name)->toBe('Dan');
    });
}
