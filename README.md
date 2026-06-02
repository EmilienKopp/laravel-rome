# Laravel Rome

Make Database Views first-class citizens in your Laravel app.

A Laravel package for managing database views: scaffolding, regeneration, and materialized view refresh. Supports PostgreSQL and MySQL, with optional multi-tenant iteration.

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
    'db_connections' => ['default'],  // e.g. ['default'] or ['analytics', 'reporting']

    // --- Multi-tenancy (optional) ---
    'tenant_model'         => null,     // e.g. App\Models\Tenant::class
    'tenant_status_column' => 'status',
    'tenant_active_status' => 'active',
];
```

## Scaffolding a view

```bash
php artisan make:dbview order_summary
```

This creates three files:

| File | Purpose |
|---|---|
| `database/views/order_summary.sql` | SQL definition — edit this |
| `database/migrations/{timestamp}_create_order_summary_view.php` | Runs the SQL on migrate |
| `app/Models/Views/OrderSummaryView.php` | Eloquent model backed by the view |

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

## ReadOnlyModel

A base Eloquent model for views. Reads behave exactly like any Eloquent model. Direct writes (`save`, `delete`) always throw. Write operations that need to mutate data are proxied through a separate writable model that maps to the underlying table.

### Setup

```php
use Splitstack\Rome\Models\ReadOnlyModel;

class OrderSummaryView extends ReadOnlyModel
{
    protected $table = 'order_summary_view';

    // Declare the writable model that owns the underlying data
    protected static $proxiedModelClass = Order::class;
}
```

**Primary key:** `ReadOnlyModel` declares a non-incrementing primary key named `id` but makes no assumption about key type. Set `$keyType`, `$incrementing`, and any `$casts` on your model to match your actual key type. The proxied model must use the same primary key name and type, since all proxy lookups use `$this->getKey()` to locate the record in the proxied table.

### Proxied writes

#### `update(array $attributes)`

Finds the matching record in the proxied model by primary key, calls `update()` on it, then re-fetches and returns the view record so computed columns reflect the change.

```php
$summary = OrderSummaryView::find($id);
$summary->update(['status' => 'shipped']); // returns OrderSummaryView
```

Throws if no matching record exists in the proxied table.

### Accessing the underlying model

#### `underlying(bool $forceFetch = false)`

Returns a proxied model instance populated with the current record's data.

**Without `forceFetch` (default):** hydrates the instance in-memory from the view's attributes, intersected with the proxied model's `$fillable`. No database query is made, but attributes not listed in `$fillable` will be absent.

```php
$order = $summary->underlying(); // no query; only $fillable attributes populated
$order->status; // available if 'status' is in Order::$fillable
```

**With `forceFetch: true`:** queries the proxied model's table directly. All attributes are present, at the cost of an extra query.

```php
$order = $summary->underlying(forceFetch: true); // hits the database
```

Use the default when you only need fillable attributes (e.g. to re-dispatch events or call methods). Use `forceFetch` when you need the complete record or attributes that aren't in `$fillable`.

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

| Feature | PostgreSQL | MySQL |
|---|---|---|
| Regular views | ✓ | ✓ |
| Materialized views | ✓ | — (skipped with warning) |
| `DROP VIEW … CASCADE` | ✓ | ✓ (omitted) |
| Unique index check | ✓ | ✓ |

## License

MIT
