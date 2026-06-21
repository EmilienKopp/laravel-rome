<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Fixtures\NoDirectWrite;

use Splitstack\Rome\Models\ReadOnlyModel;

class ViolatingView extends ReadOnlyModel {}

function violatingCalls(ViolatingView $view): void
{
    $view->save();
    $view->delete();
}
