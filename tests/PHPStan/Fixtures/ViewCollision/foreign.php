<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Fixtures\ViewCollision;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Rome\Models\ReadOnlyModel;

class ForeignProxied extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'email'];
}

// 'email' appears as orders.email in the SELECT — sourced from a joined table, not 'users',
// but collides with ForeignProxied's fillable and is not in $exclude → rome.foreignTableColumnCollision
class ForeignCollisionView extends ReadOnlyModel
{
    protected $table = 'foreign_view';

    protected static $proxyTo = ForeignProxied::class;
}

// Same collision but 'email' IS excluded → no error
class ForeignExcludedView extends ReadOnlyModel
{
    protected $table = 'foreign_excluded_view';

    protected static $proxyTo = ForeignProxied::class;

    protected static array $exclude = ['email'];
}
