<?php

namespace Splitstack\Rome\Tests\Integration\Fixtures\Models;

use Splitstack\Rome\Concerns\RefreshableMaterializedView;
use Splitstack\Rome\Models\ReadOnlyModel;

class IntegrationMaterializedViewModel extends ReadOnlyModel
{
    use RefreshableMaterializedView;

    protected $table = 'rome_integration_items_matview';

    protected $fillable = ['name', 'status', 'display_name'];

    public $timestamps = false;
}
