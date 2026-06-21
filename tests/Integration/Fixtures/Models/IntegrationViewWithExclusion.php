<?php

namespace Splitstack\Rome\Tests\Integration\Fixtures\Models;

use Splitstack\Rome\Models\ReadOnlyModel;

class IntegrationViewWithExclusion extends ReadOnlyModel
{
    protected $table = 'rome_integration_items_view';

    protected static $proxyTo = IntegrationConcreteModel::class;

    protected $fillable = ['name', 'status', 'display_name'];

    protected static array $exclude = ['display_name'];

    public $timestamps = false;
}
