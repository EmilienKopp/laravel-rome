<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Fixtures\NoDirectWrite;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Rome\Models\ReadOnlyModel;

class ValidView extends ReadOnlyModel {}

class PlainModel extends Model
{
    protected $table = 'plain';
}

function validCalls(ValidView $view, PlainModel $plain): void
{
    $view->update(['name' => 'test']); // update() is allowed — it proxies through $proxyTo
    $plain->save();   // regular Model, not blocked
    $plain->delete(); // regular Model, not blocked
}
