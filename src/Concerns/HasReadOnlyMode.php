<?php

namespace Splitstack\Rome\Concerns;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Rome\Database\ReadOnlyBuilder;
use Splitstack\Rome\Models\ReadOnlyProxy;

/**
 * Trait HasReadOnlyMode
 *
 * Provides a simple way to access a model in read-only mode, either from the original table or from a specified read-only view.
 *
 * @method ReadOnlyProxy readonly() Access the model in read-only mode, blocking write operations.
 * @method ReadOnlyProxy fromView() Access the model from a specified read-only view, blocking write operations.
 *
 * @mixin Model
 */
trait HasReadOnlyMode
{
    protected static ?string $readOnlyView = null;

    public function readonly(): ReadOnlyProxy
    {
        return new ReadOnlyProxy($this);
    }

    public static function fromView(): ReadOnlyBuilder
    {
        $instance = new static;

        if (static::$readOnlyView !== null) {
            $instance->setTable(static::$readOnlyView);
        }

        $builder = new ReadOnlyBuilder($instance->newBaseQueryBuilder());
        $builder->setModel($instance);

        return $builder;
    }
}
