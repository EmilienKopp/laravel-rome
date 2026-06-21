<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Fixtures\ProxiedWrite;

use Splitstack\Rome\Models\ReadOnlyModel;

class ViolatingProxyView extends ReadOnlyModel {}

function violatingChains(ViolatingProxyView $view): void
{
    $view->proxied()->save();
    $view->proxied()->delete();
    $view->underlying(false)->save();
    $view->underlying(false)->delete();
    $view->underlying(forceFetch: false)->save();
}
