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
    | Example: ['default'] or ['analytics', 'reporting']
    |
    */
    'db_connections' => [],

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

];
