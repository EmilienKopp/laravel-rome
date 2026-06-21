<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Fixtures\ProxiedWrite;

use Splitstack\Rome\Models\ReadOnlyModel;

class ValidProxyView extends ReadOnlyModel {}

function validCalls(ValidProxyView $view): void
{
    // forceFetch: true fetches from the real table — different rule, different node type
    $view->underlying(true)?->save();
    $view->underlying(forceFetch: true)?->save();
    $view->underlying()?->save();

    // Assigning the proxy without immediately writing through it is fine
    $proxy = $view->proxy();
    unset($proxy);
}
