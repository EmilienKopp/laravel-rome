<?php

namespace Splitstack\Rome\Models;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Rome\Exceptions\ReadOnlyModelException;

/**
 * A simple proxy wrapper for a ReadOnlyModel instance.
 * Used to block write operations when proxying is disabled, and to allow access to the underlying model's methods and properties without hydration.
 *
 * @mixin Model
 */
class ReadOnlyProxy
{
    public function __construct(private Model|self $model)
    {
        if ($model instanceof self) {
            $this->model = $model->getModel();
        }
    }

    public function __call($method, $args)
    {
        if (in_array($method, ['save', 'delete', 'update'])) {
            throw new ReadOnlyModelException;
        }

        return $this->model->$method(...$args);
    }

    public function __get($key)
    {
        return $this->model->$key;
    }

    public function __toString()
    {
        return (string) $this->model;
    }

    public function toJson($options = 0)
    {
        return $this->model->toJson($options);
    }

    public function toArray()
    {
        return $this->model->toArray();
    }

    public function toPrettyJson($options = 0)
    {
        return $this->model->toJson(JSON_PRETTY_PRINT | $options);
    }

    public function __debugInfo(): array
    {
        return $this->model->toArray();
    }

    public function getModel(): Model|self
    {
        return $this->model;
    }
}
