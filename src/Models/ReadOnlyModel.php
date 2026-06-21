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
 * @important the `proxy_enabled` config must be true for proxying to work, otherwise all proxy calls throw
 *
 * @mixin Model
 */
abstract class ReadOnlyModel extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = false;

    /**
     * The model class to proxy write operations to.
     * Setting this opts the model in to proxy operations.
     * rome.proxy_enabled must also be true, or all proxy calls throw regardless.
     *
     * @var class-string<Model>|null
     */
    protected static $proxyTo = null;

    /**
     * Attributes stripped when hydrating via proxied() / underlying(forceFetch: false).
     * Use for computed columns whose names collide with columns in the proxied table.
     */
    protected static array $exclude = [];

    /**
     * @throws ReadOnlyModelException always, because this model is read-only
     *
     * @deprecated delete() is unavailable from Read Only Models
     */
    public function delete()
    {
        throw new ReadOnlyModelException('Cannot delete from read-only model');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return static|null
     *
     * @throws ProxiedModelException if proxying is disabled or misconfigured
     */
    public function update(array $attributes = [], array $options = [])
    {
        $this->assertProxyEnabled();

        $proxiedModel = $this->getProxiedModelInstance();
        $existingRecord = $proxiedModel->find($this->getKey());

        if (! $existingRecord) {
            throw new ProxiedModelException('Record does not exist in proxied model.');
        }

        $existingRecord->update($attributes, $options);

        return static::find($this->getKey());
    }

    /**
     * @throws ReadOnlyModelException always, because this model is read-only
     *
     * @deprecated save() is unavailable from Read Only Models
     */
    public function save(array $options = [])
    {
        throw new ReadOnlyModelException('Cannot save read-only model directly. Define $proxyTo and use update() instead.');
    }

    /**
     * Returns a proxied model instance.
     * With forceFetch: true (default) queries the proxied table — all attributes present.
     * With forceFetch: false hydrates in-memory from $fillable — no query, but computed
     * column values come from the view. Audit $exclude before using this path.
     *
     * @phpstan-return ($forceFetch is true ? ?Model : Model)
     *
     * @throws ProxiedModelException if proxying is disabled or misconfigured
     */
    public function underlying(bool $forceFetch = true): ?Model
    {
        $this->assertProxyEnabled();

        if ($forceFetch) {
            $modelClass = static::getProxiedModelClass();

            return $modelClass::find($this->getKey());
        }

        $instance = new static::$proxyTo;
        $attributes = array_intersect_key($this->attributesToArray(), array_flip($instance->getFillable()));
        $attributes = $this->excludeAttributes($attributes);
        $instance = $instance->newInstance($attributes, exists: true);
        // Set the PK directly — newInstance() fills via mass-assignment so the key
        // is silently dropped unless it's in $fillable.
        $instance->setAttribute($instance->getKeyName(), $this->getKey());
        $instance->wasRecentlyCreated = false;

        return $instance;
    }

    /**
     * Hydrates a proxied model instance in-memory from the view's attributes.
     * Alias for underlying(forceFetch: false).
     *
     * @throws ProxiedModelException if proxying is disabled or misconfigured
     */
    public function proxied(): Model
    {
        return $this->underlying(forceFetch: false);
    }

    protected function getProxiedModelInstance(): Model
    {
        return new (static::getProxiedModelClass());
    }

    /**
     * @throws ProxiedModelException if proxying is disabled or misconfigured
     */
    protected static function getProxiedModelClass(): string
    {
        if (! static::$proxyTo) {
            throw new ProxiedModelException('No proxy target defined. Set $proxyTo on '.static::class.'.');
        }

        if (! class_exists(static::$proxyTo)) {
            throw new ProxiedModelException("Proxy target '".static::$proxyTo."' does not exist.");
        }

        return static::$proxyTo;
    }

    /**
     * @throws ProxiedModelException if proxying is disabled or misconfigured
     */
    private function assertProxyEnabled(): void
    {
        if (! config('rome.proxy_enabled', false)) {
            throw new ProxiedModelException(
                'Proxy operations are disabled globally. Set rome.proxy_enabled to true in config/rome.php. '.
                'Read the warning in that config before enabling.'
            );
        }

        if (! static::$proxyTo) {
            throw new ProxiedModelException(
                'No proxy target defined. Set $proxyTo on '.static::class.'.'
            );
        }
    }

    private function excludeAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(static::$exclude));
    }
}
