<?php

namespace Splitstack\Rome\Models;

use Illuminate\Database\Eloquent\Model;
use Splitstack\Rome\Exceptions\ProxiedModelException;
use Splitstack\Rome\Exceptions\ReadOnlyModelException;

/**
 * A base model for read-only database views.
 * Prevents direct writes, but allows updates via a proxied model class.
 *
 * @important for the proxying to work, the proxied model must use the same primary key name and type
 */
abstract class ReadOnlyModel extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = false;

    /**
     * The model class to proxy write operations to.
     * Must be defined in child classes.
     *
     * @var class-string<Model>|null
     */
    protected static $proxiedModelClass = null;

    /**
     * @throws ReadOnlyModelException
     */
    public function delete()
    {
        throw new ReadOnlyModelException('Cannot delete from read-only model');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return static|null
     *
     * @throws ProxiedModelException
     */
    public function update(array $attributes = [], array $options = [])
    {
        $proxiedModel = $this->getProxiedModelInstance();
        $existingRecord = $proxiedModel->find($this->getKey());
        if (! $existingRecord) {
            throw new ProxiedModelException('Record does not exist in proxied model.');
        }
        $existingRecord->update($attributes, $options);

        return static::find($this->getKey());
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws ReadOnlyModelException
     */
    public function save(array $options = [])
    {
        throw new ReadOnlyModelException('Cannot save read-only model directly. Define a "proxiedModelClass" and use update() instead.');
    }

    /**
     * Get an instance of the proxied model.
     */
    protected function getProxiedModelInstance(): Model
    {
        $modelClass = static::getProxiedModelClass();

        return new $modelClass;
    }

    /**
     * Hydrate an instance of the proxied model with attributes from this read-only model
     * without a database query.
     */
    public function underlying(bool $forceFetch = false): ?Model
    {
        if ($forceFetch) {
            $modelClass = static::getProxiedModelClass();

            return $modelClass::find($this->getKey());
        }
        $instance = new static::$proxiedModelClass;
        $attributes = array_intersect_key($this->attributesToArray(), array_flip($instance->getFillable()));
        $instance = $instance->newInstance($attributes, exists: true);
        $instance->wasRecentlyCreated = false;

        return $instance;
    }

    /**
     * Hydrate an instance of the proxied model with attributes from this read-only model
     * without a database query.
     * Alias for underlying()
     */
    public function proxy(): Model
    {
        return $this->underlying();
    }

    /**
     * Get the proxied model class name.
     *
     * @throws ProxiedModelException
     */
    protected static function getProxiedModelClass(): string
    {
        if (! static::$proxiedModelClass) {
            throw new ProxiedModelException('Proxied model class not defined. Set $proxiedModelClass in your model.');
        }

        if (! class_exists(static::$proxiedModelClass)) {
            throw new ProxiedModelException("Proxied model class '".static::$proxiedModelClass."' does not exist.");
        }

        return static::$proxiedModelClass;
    }
}
