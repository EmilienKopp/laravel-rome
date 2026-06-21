<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Fixtures\ViewCollision;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Rome\Models\ReadOnlyModel;

class ViolatingProxied extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'status'];
}

// 'name' is computed in the SQL (CONCAT ... AS name) and is fillable on ViolatingProxied,
// but is not listed in $exclude → should trigger rome.proxyComputedColumnCollision
class ViolatingCollisionView extends ReadOnlyModel
{
    protected $table = 'violating_view';

    protected static $proxyTo = ViolatingProxied::class;
}
