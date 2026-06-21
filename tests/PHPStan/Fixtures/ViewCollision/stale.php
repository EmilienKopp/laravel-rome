<?php

declare(strict_types=1);

namespace Splitstack\Rome\Tests\PHPStan\Fixtures\ViewCollision;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Rome\Models\ReadOnlyModel;

class StaleProxied extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'email'];
}

// 'full_name' is in $exclude but never appears in the SQL → should trigger rome.staleExcludeEntry
class StaleExcludeView extends ReadOnlyModel
{
    protected $table = 'stale_view';

    protected static $proxyTo = StaleProxied::class;

    protected static array $exclude = ['full_name'];
}
