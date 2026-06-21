<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Fixtures\ProxiedWrite;

use Splitstack\Rome\Models\ReadOnlyModel;

class ViolatingProxyView extends ReadOnlyModel {}

function violatingChains(ViolatingProxyView $view): void
{
    $view->proxy()->save();
    $view->proxy()->delete();
    $view->underlying(false)->save();
    $view->underlying(false)->delete();
    $view->underlying(forceFetch: false)->save();
}
