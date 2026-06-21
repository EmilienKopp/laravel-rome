<?php

use Splitstack\Rome\Tests\Integration\Support\SchemaHelper;

use function Splitstack\Rome\Tests\Integration\Shared\registerExcludeBehaviourTests;
use function Splitstack\Rome\Tests\Integration\Shared\registerProxyBehaviourTests;
use function Splitstack\Rome\Tests\Integration\Shared\registerReadOnlyModelBehaviourTests;

describe('ReadOnlyModel — PostgreSQL', function () {
    beforeEach(function () {
        $this->helper = new SchemaHelper('pgsql_test');
        $this->helper->createItemsTableAndView();
    });

    afterEach(function () {
        $this->helper?->dropItemsTableAndView();
    });

    registerReadOnlyModelBehaviourTests();
    registerProxyBehaviourTests();
    registerExcludeBehaviourTests();
});
