<?php

namespace Splitstack\Rome\Tests\Integration\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConcreteModel extends Model
{
    protected $table = 'rome_integration_items';

    protected $fillable = ['name', 'status'];

    public $timestamps = false;
}
