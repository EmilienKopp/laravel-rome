<?php

namespace Splitstack\Rome\Database;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Splitstack\Rome\Exceptions\ReadOnlyModelException;
use Splitstack\Rome\Models\ReadOnlyProxy;

class ReadOnlyBuilder extends Builder
{
    public function __construct($query)
    {
        parent::__construct($query);
    }

    /**
     * @return never
     *
     * @throws ReadOnlyModelException
     */
    public function update(array $values)
    {
        throw new ReadOnlyModelException('Cannot bulk update via a read-only view query.');
    }

    /**
     * @return never
     *
     * @throws ReadOnlyModelException
     */
    public function delete()
    {
        throw new ReadOnlyModelException('Cannot bulk delete via a read-only view query.');
    }

    public function create(array $attributes = [])
    {
        throw new ReadOnlyModelException('Cannot create via a read-only view query.');
    }

    /**
     * Get the first record matching the attributes. If the record is not found, create it.
     *
     * @param  (Closure(): array)|array  $values
     *
     * @throws ReadOnlyModelException
     */
    public function firstOrCreate(array $attributes = [], Closure|array $values = [])
    {
        throw new ReadOnlyModelException('Cannot create via a read-only view query.');
    }

    /**
     * @return Collection<ReadOnlyProxy>
     */
    public function get($columns = ['*']): Collection
    {
        /**
         * @var Collection<Model>
         */
        $results = parent::get($columns);

        return $results->map(fn ($model) => new ReadOnlyProxy($model));
    }

    public function first($columns = ['*']): ?ReadOnlyProxy
    {
        $result = parent::first($columns);

        return $result ? new ReadOnlyProxy($result) : null;
    }

    public function sole($columns = ['*']): ReadOnlyProxy
    {
        $result = parent::sole($columns);

        return new ReadOnlyProxy($result);
    }

    // Throw on cursos(), each(), chunk(), ...
    public function cursor()
    {
        throw new ReadOnlyModelException('Cannot use cursor() on a read-only view query.');
    }

    public function each($callback, $count = 1000)
    {
        throw new ReadOnlyModelException('Cannot use each() on a read-only view query.');
    }

    public function chunk($count, $callback)
    {
        throw new ReadOnlyModelException('Cannot use chunk() on a read-only view query.');
    }

    // find() uses first() under the hood, so it doesn't need to be overridden
    // findOrFail() uses find() under the hood, so it doesn't need to be overridden
}
