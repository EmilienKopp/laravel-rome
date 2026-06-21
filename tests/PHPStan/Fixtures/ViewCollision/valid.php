<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Fixtures\ViewCollision;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Rome\Models\ReadOnlyModel;

class ValidProxied extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'status'];
}

// 'name' is computed in the SQL and is fillable on ValidProxied,
// but IS listed in $exclude → no error
class ValidExcludedView extends ReadOnlyModel
{
    protected $table = 'valid_view';

    protected static $proxyTo = ValidProxied::class;

    protected static array $exclude = ['name'];
}

// No $proxyTo set → rule skips, no error
class ValidNoProxyView extends ReadOnlyModel
{
    protected $table = 'valid_view';
}
