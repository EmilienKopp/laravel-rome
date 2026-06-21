<?php

namespace Splitstack\Rome\Tests\Integration\Fixtures\Models;

use Splitstack\Rome\Models\ReadOnlyModel;

class IntegrationViewWithProxy extends ReadOnlyModel
{
    protected $table = 'rome_integration_items_view';

    protected static $proxyTo = IntegrationConcreteModel::class;

    protected $fillable = ['name', 'status', 'display_name'];

    public $timestamps = false;
}
