<?php

return [

    'db_views_path' => database_path('views'),

    /*
    |--------------------------------------------------------------------------
    | Priority Views
    |--------------------------------------------------------------------------
    |
    | Views listed here are regenerated first, in order, before all others.
    | Use this when some views depend on other views being created first.
    |
    | Example: ['base_metrics', 'aggregated_totals']
    |
    */
    'priority_views' => [],

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | The database connections used for view operations (regeneration, refresh).
    | Views will be run against each connection in order.
    |
    | Example: ['pgsql'] or ['analytics', 'reporting']
    |
    */
    'db_connections' => [env('DB_CONNECTION', 'mysql')],

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model class used to look up tenants when running commands
    | with --multi-tenant. Must have a status column and a name attribute.
    |
    | Example: App\Models\Tenant::class
    |
    */
    'tenant_model' => null,

    /*
    | Column used to filter active tenants.
    */
    'tenant_status_column' => 'status',

    /*
    | The value of the status column that identifies an active tenant.
    */
    'tenant_active_status' => 'active',

    /*
    |--------------------------------------------------------------------------
    | Model Scan Paths
    |--------------------------------------------------------------------------
    |
    | Directories (relative to app_path()) scanned when the make:dbview command
    | offers a model picklist. App\Models is always included automatically.
    |
    | Example: ['Models/Billing', 'Domain/Orders/Models']
    |
    */
    'model_scan_paths' => [],

    /*
    |--------------------------------------------------------------------------
    | Read-Only Model Output Path
    |--------------------------------------------------------------------------
    |
    | Directory (relative to app_path()) where make:dbview places generated
    | read-only view models.
    |
    | Example: 'Models/ReadOnly' or 'Domain/Views/Models'
    |
    */
    'readonly_model_path' => 'Models/Views',

    /*
    |--------------------------------------------------------------------------
    | Proxy Writes
    |--------------------------------------------------------------------------
    |
    | WARNING: enabling this allows ReadOnlyModel to route write operations
    | (calls to update(), proxied(), underlying()) through a separate "proxied" model that maps
    | to the underlying table. This is powerful but carries real data-corruption
    | risk if your view computes columns whose names collide with columns in the
    | underlying table. Before enabling:
    |
    |   1. Alias every computed column in your view SQL to a distinct name, OR
    |   2. List colliding column names in $exclude on your ReadOnlyModel subclass.
    |
    | Leaving this false (the default) causes those methods to throw immediately,
    | which is the safe starting point.
    |
    */
    'proxy_enabled' => env('ROME_PROXY_ENABLED', false),

];
