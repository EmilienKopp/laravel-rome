<?php

namespace Splitstack\Rome\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Splitstack\Rome\Database\ViewDialect;
use Splitstack\Rome\Exceptions\RomeConfigurationException;
use Splitstack\Rome\Exceptions\ViewRegenerationException;

class RegenerateViewCommand extends Command
{
    protected $signature = 'dbview:regen
        {name? : The name of the view to regenerate}
        {--multi-tenant : Iterate views across all active tenants using the configured tenant model}
        {--tenants= : Comma-separated tenant IDs to process (implies --multi-tenant)}
        {--no-materialized : Do not create materialized views}
        {--dry-run : Show which views would be regenerated without actually running the SQL}
    ';

    protected $description = 'Regenerate database views from SQL files';

    /**
     * Views that should be processed first due to dependencies.
     *
     * @var array<int, string>
     */
    protected array $priorityViews = [];

    public function handle(): void
    {
        $this->priorityViews = config('rome.priority_views', []);

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — no SQL will be executed.');
            $this->newLine();
        }

        $viewName = $this->argument('name');

        if ($viewName !== null && ! preg_match('/^[a-z][a-z0-9_]*$/', $viewName)) {
            $this->error('Invalid view name. Use only lowercase letters, digits, and underscores (must start with a letter).');

            return;
        }

        $viewsPath = config('rome.db_views_path', database_path('views'));

        if ($this->option('multi-tenant') || $this->option('tenants')) {
            $this->handleMultiTenant($viewName, $viewsPath);
        } else {
            $this->handleSingle($viewName, $viewsPath);
        }
    }

    protected function handleSingle(?string $viewName, string $viewsPath): void
    {
        $connections = $this->resolveConnections();
        $multipleConnections = count($connections) > 1;

        foreach ($connections as $connection) {
            if ($multipleConnections) {
                $this->info("Connection: {$connection}");
            }

            $failedViews = $viewName
                ? $this->regenerateView($viewName, $viewsPath, $connection)
                : $this->regenerateAllViews($viewsPath, $connection);

            if (empty($failedViews)) {
                $suffix = $multipleConnections ? " on {$connection}" : '';
                $this->info("✓ All views regenerated successfully{$suffix}.");
            } else {
                foreach ($failedViews as $failed) {
                    $this->error("✗ {$failed['view']}: {$failed['error']}");
                }
                throw new ViewRegenerationException('One or more views failed to regenerate. See above for details.');
            }
        }
    }

    protected function handleMultiTenant(?string $viewName, string $viewsPath): void
    {
        $tenantsOption = $this->option('tenants');

        if ($tenantsOption && ! preg_match('/^([a-zA-Z0-9_-]+,?)+$/', $tenantsOption)) {
            $this->error('Invalid --tenants option format. Use comma-separated tenant IDs.');

            return;
        }

        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return;
        }

        $this->info("Found {$tenants->count()} active tenants.");
        $this->newLine();

        $successCount = 0;
        $tenantFailures = [];

        $tenants->eachCurrent(function ($tenant) use ($viewName, $viewsPath, &$successCount, &$tenantFailures) {
            $this->info("Processing tenant: {$tenant->name}");

            $connections = $this->resolveConnections();
            $allFailedViews = [];

            foreach ($connections as $connection) {
                $failed = $viewName
                    ? $this->regenerateView($viewName, $viewsPath, $connection)
                    : $this->regenerateAllViews($viewsPath, $connection);

                foreach ($failed as $entry) {
                    $allFailedViews[] = array_merge($entry, ['connection' => $connection]);
                }
            }

            if (empty($allFailedViews)) {
                $successCount++;
                $this->info("  ✓ All views regenerated successfully for {$tenant->name}");
            } else {
                $this->error("  ✗ Failed views for {$tenant->name}:");
                foreach ($allFailedViews as $failed) {
                    $this->error("    - [{$failed['connection']}] {$failed['view']}: {$failed['error']}");
                }
                $tenantFailures[$tenant->name] = $allFailedViews;
            }

            $this->newLine();
        });

        $this->printSummary($successCount, $tenantFailures);
    }

    /**
     * @return array<int, string>
     */
    protected function resolveConnections(): array
    {
        $connections = config('rome.db_connections', []);

        if (empty($connections)) {
            throw new RomeConfigurationException(
                'rome.db_connections is empty. Configure it in config/rome.php.'
            );
        }

        return $connections;
    }

    /**
     * @return Collection<int, Model>
     */
    protected function resolveTenants(): Collection
    {
        $modelClass = config('rome.tenant_model');

        if (! $modelClass || ! class_exists($modelClass)) {
            throw new RomeConfigurationException(
                'rome.tenant_model is not configured or the class does not exist. '.
                'Publish the config and set it in config/rome.php.'
            );
        }

        $statusColumn = config('rome.tenant_status_column', 'status');
        $activeStatus = config('rome.tenant_active_status', 'active');
        $tenantsOption = $this->option('tenants');

        return $modelClass::where($statusColumn, $activeStatus)
            ->when($tenantsOption, function ($q) use ($tenantsOption) {
                $q->whereIn('id', explode(',', $tenantsOption));
            })
            ->get();
    }

    /**
     * @param  array<string, array<int, array<string, string>>>  $tenantFailures
     */
    protected function printSummary(int $successCount, array $tenantFailures): void
    {
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('View Regeneration Summary');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("✓ Successfully processed: {$successCount} tenants");
        $this->info('✗ Failed: '.count($tenantFailures).' tenants');

        if (! empty($tenantFailures)) {
            $this->newLine();
            $this->error('Failed Views by Tenant:');
            foreach ($tenantFailures as $tenantName => $failedViews) {
                $this->error("  {$tenantName}:");
                foreach ($failedViews as $failed) {
                    $this->error("    - {$failed['view']}");
                }
            }

            throw new ViewRegenerationException('One or more tenants had view regeneration failures. See above for details.');
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function regenerateView(string $viewName, string $viewsPath, string $connection): array
    {
        $sqlFile = $viewsPath.'/'.$viewName.'.sql';

        if (! File::exists($sqlFile)) {
            return [['view' => $viewName, 'error' => "View file not found: {$sqlFile}"]];
        }

        try {
            $sql = File::get($sqlFile);
            $isMaterialized = $this->isMaterializedView($sql);
            $dialect = ViewDialect::fromConnection($connection);

            if ($isMaterialized && ! $dialect->supportsMaterializedViews()) {
                $this->warn("  ⚠ Skipping materialized view '{$viewName}': not supported by driver '{$dialect->driver()}'.");

                return [];
            }

            if ($isMaterialized && $this->option('no-materialized')) {
                $this->line("  ⊘ Skipping materialized view: {$viewName}");

                return [];
            }

            $type = $isMaterialized ? 'materialized view' : 'view';

            if ($this->option('dry-run')) {
                $this->line("  ~ Would regenerate: <fg=cyan>{$viewName}</> ({$type}) on {$connection}");

                return [];
            }

            $viewNames = [$viewName, $viewName.'_view'];
            $success = false;
            $lastError = null;

            foreach ($viewNames as $vName) {
                try {
                    $dropSql = $isMaterialized
                        ? $dialect->dropMaterializedView($vName)
                        : $dialect->dropView($vName);

                    DB::connection($connection)->unprepared($dropSql);
                    DB::connection($connection)->unprepared($sql);
                    $success = true;
                    break;
                } catch (\Exception $e) {
                    $lastError = $e;
                }
            }

            if (! $success) {
                return [['view' => $viewName, 'error' => 'Failed with either naming pattern. Last error: '.$lastError->getMessage()]];
            }

            return [];
        } catch (\Exception $e) {
            return [['view' => $viewName, 'error' => $e->getMessage()]];
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function regenerateAllViews(string $viewsPath, string $connection): array
    {
        if (! File::exists($viewsPath)) {
            return [['view' => 'all', 'error' => "Views directory not found: {$viewsPath}"]];
        }

        $dialect = ViewDialect::fromConnection($connection);

        $files = collect(File::files($viewsPath))
            ->sortBy(function ($file) {
                $name = $file->getBasename('.sql');
                $priority = array_search($name, $this->priorityViews);

                return $priority !== false
                    ? sprintf('%03d_%s', $priority, $name)
                    : sprintf('999_%s', $name);
            })
            ->values()
            ->all();

        $failedViews = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'sql') {
                continue;
            }

            $viewName = $file->getBasename('.sql');

            try {
                $sql = File::get($file->getPathname());
                $isMaterialized = $this->isMaterializedView($sql);

                if ($isMaterialized && ! $dialect->supportsMaterializedViews()) {
                    $this->warn("  ⚠ Skipping materialized view '{$viewName}': not supported by driver '{$dialect->driver()}'.");

                    continue;
                }

                if ($isMaterialized && $this->option('no-materialized')) {
                    $this->line("  ⊘ Skipping materialized view: {$viewName}");

                    continue;
                }

                $type = $isMaterialized ? 'materialized view' : 'view';
                $priority = in_array($viewName, $this->priorityViews) ? ' <fg=yellow>[priority]</>' : '';

                if ($this->option('dry-run')) {
                    $this->line("  ~ Would regenerate: <fg=cyan>{$viewName}</>{$priority} ({$type}) on {$connection}");

                    continue;
                }

                $viewNames = [$viewName, $viewName.'_view'];
                $success = false;
                $lastError = null;

                foreach ($viewNames as $vName) {
                    try {
                        $dropSql = $isMaterialized
                            ? $dialect->dropMaterializedView($vName)
                            : $dialect->dropView($vName);

                        DB::connection($connection)->unprepared($dropSql);
                        DB::connection($connection)->unprepared($sql);
                        $success = true;
                        break;
                    } catch (\Exception $e) {
                        $lastError = $e;
                    }
                }

                if (! $success) {
                    $failedViews[] = ['view' => $viewName, 'error' => 'Failed with either naming pattern. Last error: '.$lastError->getMessage()];
                }
            } catch (\Exception $e) {
                $failedViews[] = ['view' => $viewName, 'error' => $e->getMessage()];
            }
        }

        return $failedViews;
    }

    protected function isMaterializedView(string $sql): bool
    {
        $normalized = preg_replace('/--[^\n]*/', '', $sql);
        $normalized = preg_replace('/\/\*[\s\S]*?\*\//', '', $normalized);
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $normalized)));

        return str_contains($normalized, 'CREATE MATERIALIZED VIEW') ||
               str_contains($normalized, 'CREATE OR REPLACE MATERIALIZED VIEW');
    }
}
