<?php

namespace Splitstack\Rome\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\SerializableClosure\SerializableClosure;
use Splitstack\Rome\Database\MaterializedViewRefresher;
use Splitstack\Rome\Exceptions\RomeConfigurationException;

class RefreshMaterializedView implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 60;

    /** @var array<int, SerializableClosure|callable> */
    public array $onFailure = [];

    public function __construct(
        public string $viewName,
        public bool $concurrent = false,
        public ?string $tenantId = null,
        public ?string $connection = null,
        array $onFailure = [],
    ) {
        $this->onFailure = array_map(
            static fn ($cb) => $cb instanceof \Closure ? new SerializableClosure($cb) : $cb,
            $onFailure
        );
    }

    public function handle(): void
    {
        $connection = $this->connection
            ?? collect(config('rome.db_connections', []))->first()
            ?? throw new RomeConfigurationException(
                'No connection specified and rome.db_connections is empty. '.
                'Set it in config/rome.php or pass $connection to the job constructor.'
            );

        $lockKey = "refresh_mat_view_{$this->tenantId}_{$this->viewName}";
        $lock = Cache::lock($lockKey, $this->timeout);

        if (! $lock->get()) {
            Log::info('Skipping materialized view refresh — another refresh is already in progress', [
                'view_name' => $this->viewName,
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        try {
            $startTime = microtime(true);

            (new MaterializedViewRefresher($connection))->refresh($this->viewName, $this->concurrent);

            Log::info('Materialized view refreshed successfully', [
                'view_name' => $this->viewName,
                'concurrent' => $this->concurrent,
                'tenant_id' => $this->tenantId,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'job_id' => $this->job?->getJobId(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to refresh materialized view', [
                'view_name' => $this->viewName,
                'concurrent' => $this->concurrent,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'job_id' => $this->job?->getJobId(),
                'attempt' => $this->attempts(),
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Materialized view refresh job failed permanently', [
            'view_name' => $this->viewName,
            'concurrent' => $this->concurrent,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        foreach ($this->onFailure as $callback) {
            $callback($exception, $this);
        }
    }
}
