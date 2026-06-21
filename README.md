<p align="center">
  <img src="art/logo.png" width="120" alt="Laravel Rome">
</p>

# Laravel Rome

![Tests](https://img.shields.io/github/actions/workflow/status/emilienkopp/laravel-rome/tests.yml?label=tests)
![PHP Version](https://img.shields.io/badge/php-^8.2-blue.svg?style=flat-square)
![Laravel Version](https://img.shields.io/badge/laravel-^11.0-orange.svg?style=flat-square)
[![Total Downloads](https://img.shields.io/packagist/dt/splitstack/laravel-rome.svg?style=flat-square)](https://packagist.org/packages/splitstack/laravel-rome)

Make database views first-class citizens in your Laravel app.

Laravel Rome takes the friction out of working with database views: query them through proper Eloquent models, but also scaffold them or refresh materialized ones. Works with PostgreSQL and MySQL, with optional multi-tenant support.

## Requirements

- PHP 8.2+
- Laravel 11+
- Database: PostgreSQL 9.3+ or MySQL 5.7+

## Installation

```bash
composer require splitstack/laravel-rome
```

Publish the config:

```bash
php artisan vendor:publish --tag=rome-config
```

## Configuration

```php
// config/rome.php

return [
    // Path where your .sql view files live
    'db_views_path' => database_path('views'),

    // Connections used for view operations. Must be configured.
    // Views are run against each connection in order.
    'db_connections' => ['pgsql'],  // e.g. ['pgsql'] or ['analytics', 'reporting']

    // --- Multi-tenancy (optional) ---
    'tenant_model'         => null,     // e.g. App\Models\Tenant::class
    'tenant_status_column' => 'status',
    'tenant_active_status' => 'active',

    // Directories scanned when make:dbview offers the model picklist.
    // App\Models is always included. Paths are relative to app_path().
    'model_scan_paths' => [],           // e.g. ['Domain/Orders/Models']

    // Where make:dbview places generated read-only view models.
    // Path is relative to app_path(); namespace is derived automatically.
    'readonly_model_path' => 'Models/Views',
];
```

## ReadOnlyModel

`ReadOnlyModel` is the Eloquent model you point at a database view. Reading from it works exactly like any other model. Writing directly with `save()` or `delete()` is intentionally blocked.
However, we provide a fluent way to proxy the underlying writable model for updates, and to access the underlying model instance for event dispatch or method calls.

### Enabling proxy operations

Proxy operations (`update`, `underlying`, `proxy`) can be dangerous if you are not careful, so they are **disabled by default**. Two conditions must both be true or every proxy call throws a `ProxiedModelException`:

1. **Global switch** — set `rome.proxy_enabled => true` in `config/rome.php`. Read the warning comment there before enabling.
2. **Per-model opt-in** — set `$proxyTo` on your model to the writable model class that owns the underlying table. Leaving it `null` keeps the model's proxy disabled even when the global switch is on.

### Setup

| Property   | Type                 | Purpose                                                                                                                                                                         |
| ---------- | -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `$table`   | `string`             | The view name in the database                                                                                                                                                   |
| `$proxyTo` | `class-string\|null` | Writable model that owns the underlying table. Required to enable proxy operations                                                                                              |
| `$exclude` | `string[]`           | Columns stripped when hydrating via `proxy()` / `underlying(false)`. See [computed column warning](#danger-computed-columns-that-share-a-name-with-the-underlying-table-column) |

```php
use Splitstack\Rome\Models\ReadOnlyModel;

class OrderSummaryView extends ReadOnlyModel
{
    protected $table = 'order_summary_view';

    protected $primaryKey = 'id'; // defaults to 'id' if omitted; override if your view's primary key is different

    protected static $proxyTo = Order::class;

    protected static array $exclude = ['total_price'];
}
```

**Primary key:** `ReadOnlyModel` declares a non-incrementing primary key named `id` but makes no assumption about key type. Set `$keyType`, `$incrementing`, and any `$casts` on your model to match your actual key type. The model set in `$proxyTo` must use the same primary key name and type, since all proxy lookups use `$this->getKey()` to locate the record in the proxied table.
**Make sure to override `protected $primaryKey` if your view's primary key is not `id`.**

### Proxied writes

#### `update(array $attributes)`

Looks up the matching record in the proxied model by primary key, updates it, then re-fetches and returns the view record so your computed columns are up to date.

```php
$summary = OrderSummaryView::find($id);
$summary->update(['status' => 'shipped']); // returns OrderSummaryView
```

Throws if no matching record exists in the proxied table.

### Accessing the underlying model

#### `underlying(bool $forceFetch = true)`

Returns a proxied model instance. The default is `forceFetch: true` — it queries the proxied model's table directly so all attributes are present and reflect the real stored values.

```php
$order = $summary->underlying(); // hits the database; all attributes present
```

Pass `forceFetch: false` to hydrate in-memory from the view's attributes intersected with the proxied model's `$fillable`. No database query is made, but attributes not in `$fillable` are absent, and **computed column values are taken from the view** — see the warning below.

```php
$order = $summary->underlying(forceFetch: false); // no query; $fillable attributes only, faster but riskier
```

#### `proxy()`

Alias for `underlying(forceFetch: false)`. Intended for cases where you need a writable model instance for event dispatch, method calls, or other non-persistence uses and can accept the in-memory hydration trade-offs.

```php
$order = $summary->proxy(); // no query; $fillable attributes hydrated from the view
```

---

> **Danger: computed columns that share a name with the underlying table column**
>
> If your view computes a value under the same column name that exists in the proxied model's table, `proxy()` and `underlying(forceFetch: false)` will silently hydrate the proxied instance with the **computed value from the view**, not the raw stored value. Calling `save()` or `update()` on that instance can then write the computed value back to the table, corrupting data.
>
> Example: a view computes `total_price` as `quantity * unit_price`. The `orders` table also has a stored `total_price` column. Calling `proxy()` populates `$order->total_price` with the view-computed figure. If that instance is then updated, the computed figure overwrites the stored one.
>
> **The safest fix is to rename computed columns in the view SQL** so they cannot collide:
>
> ```sql
> SELECT
>     quantity * unit_price AS computed_total_price,  -- unambiguous alias
>     item_count            AS computed_item_count
> FROM orders
> ```
>
> When renaming is not possible (e.g. the view is shared or generated), use `$exclude` to strip the dangerous attributes before hydration:
>
> ```php
> class OrderSummaryView extends ReadOnlyModel
> {
>     protected static $proxyTo = Order::class;
>
>     // Stripped when hydrating via proxy() / underlying(false)
>     protected static array $exclude = ['total_price', 'item_count'];
> }
> ```
>
> `$exclude` has no effect on `underlying(forceFetch: true)`, which always reads from the database. Use `forceFetch: true` (the default) whenever you intend to write back through the proxied model. Only use `proxy()` or `underlying(false)` when you explicitly do not need the stored values and have audited both your column aliases and your `$exclude` list.

---

## Scaffolding a view

```bash
php artisan make:dbview order_summary
```

The command prompts for the view name if omitted, then offers an interactive picklist of Eloquent models in your `app/Models` directory (and any paths listed in `rome.model_scan_paths`). Selecting a model seeds the `SELECT` column list and the view model's `$fillable` from that model's `$fillable`. Choose `(none)` to start with a blank template.

You can bypass the prompt in scripts:

```bash
php artisan make:dbview order_summary --model="App\Models\Order"
```

This creates three files:

| File                                                            | Purpose                           |
| --------------------------------------------------------------- | --------------------------------- |
| `database/views/order_summary.sql`                              | SQL definition — edit this        |
| `database/migrations/{timestamp}_create_order_summary_view.php` | Runs the SQL on migrate           |
| `app/Models/Views/OrderSummaryView.php`                         | Eloquent model backed by the view |

The output path for view models is controlled by `rome.readonly_model_path`.

## Regenerating views

Re-runs all `.sql` files in `db_views_path` against each configured connection, handling drop-and-recreate and view dependencies.

If some views depend on others existing first, declare them in `priority_views` in the config — they are created in the listed order before all remaining views (which are sorted alphabetically):

```php
'priority_views' => ['base_metrics', 'aggregated_totals'],
```

```bash
# all views, all configured connections
php artisan dbview:regen

# single view
php artisan dbview:regen order_summary

# skip materialized views
php artisan dbview:regen --no-materialized

# preview which views would run without executing any SQL
php artisan dbview:regen --dry-run
```

### Multi-tenant mode

When `tenant_model` is configured, `--multi-tenant` iterates over all active tenants using `eachCurrent` (compatible with [spatie/laravel-multitenancy](https://github.com/spatie/laravel-multitenancy)):

```bash
# all active tenants
php artisan dbview:regen --multi-tenant

# specific tenants
php artisan dbview:regen --tenants=abc123,def456
```

## Refreshing materialized views (PostgreSQL only)

### Via the job

```php
use Splitstack\Rome\Jobs\RefreshMaterializedView;

// Basic dispatch
RefreshMaterializedView::dispatch(viewName: 'order_summary_view');

// Concurrent refresh (requires a unique index on the view)
RefreshMaterializedView::dispatch(
    viewName: 'order_summary_view',
    concurrent: true,
);

// Explicit connection and tenant context
RefreshMaterializedView::dispatch(
    viewName: 'order_summary_view',
    concurrent: true,
    tenantId: $tenant->id,   // scopes the dedup lock; does not perform tenant switching
    connection: 'analytics', // overrides rome.db_connections
);

// Custom failure callbacks (closures are serialized automatically)
RefreshMaterializedView::dispatch(
    viewName: 'order_summary_view',
    onFailure: [
        fn (\Throwable $e, $job) => \Sentry\captureException($e),
        fn (\Throwable $e, $job) => Notification::send($admin, new ViewRefreshFailed($job->viewName, $e)),
    ],
);
```

The job includes a distributed lock so concurrent dispatches for the same view/tenant are deduplicated rather than stacked.

Job defaults: 3 tries, 5-minute timeout, 60-second backoff.

### Directly

```php
use Splitstack\Rome\Database\MaterializedViewRefresher;

(new MaterializedViewRefresher('analytics'))->refresh('order_summary_view', concurrent: true);
```

### RefreshableMaterializedView trait

Add to any model backed by a materialized view for convenience dispatch methods:

```php
use Splitstack\Rome\Concerns\RefreshableMaterializedView;

class OrderSummaryView extends ReadOnlyModel
{
    use RefreshableMaterializedView;
}

// Queue a refresh
OrderSummaryView::queueRefresh(concurrent: true, queue: 'low');

// Queue a refresh with tenant context (tenant switching is the caller's responsibility)
OrderSummaryView::queueRefresh(tenantId: $tenant->id, connection: 'analytics');

// Queue a delayed refresh
OrderSummaryView::queueRefreshIn(seconds: 30, concurrent: true, queue: 'low');

// Dispatch synchronously (blocks until complete, goes through the job's lock + logging)
OrderSummaryView::refreshNow(concurrent: true);
```

## ViewDialect

Driver-aware SQL builder. Used internally but available if you need to generate view DDL yourself:

```php
use Splitstack\Rome\Database\ViewDialect;

$dialect = ViewDialect::fromConnection('analytics');

$dialect->driver();                              // 'pgsql' | 'mysql'
$dialect->supportsMaterializedViews();           // true on pgsql, false on mysql
$dialect->dropView('order_summary_view');        // driver-appropriate DROP VIEW
$dialect->dropMaterializedView('...');           // pgsql only, throws on mysql
$dialect->refreshMaterializedView('...', true);  // REFRESH ... CONCURRENTLY
$dialect->uniqueIndexSql();                      // pg_indexes / information_schema query
```

## Database support

| Feature               | PostgreSQL | MySQL                    |
| --------------------- | ---------- | ------------------------ |
| Regular views         | ✓          | ✓                        |
| Materialized views    | ✓          | — (skipped with warning) |
| `DROP VIEW … CASCADE` | ✓          | ✓ (omitted)              |
| Unique index check    | ✓          | ✓                        |

## License

MIT
