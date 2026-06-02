<?php

namespace Splitstack\Rome\Concerns;

use Splitstack\Rome\Jobs\RefreshMaterializedView;

trait RefreshableMaterializedView
{
    public static function getMaterializedViewName(): string
    {
        return (new static)->getTable();
    }

    public static function queueRefresh(
        bool $concurrent = false,
        ?string $queue = null,
        ?string $tenantId = null,
        ?string $connection = null,
    ): void {
        $job = RefreshMaterializedView::dispatch(
            viewName: static::getMaterializedViewName(),
            concurrent: $concurrent,
            tenantId: $tenantId,
            connection: $connection,
        );

        if ($queue !== null) {
            $job->onQueue($queue);
        }
    }

    public static function queueRefreshIn(
        int $seconds,
        bool $concurrent = false,
        ?string $queue = null,
        ?string $tenantId = null,
        ?string $connection = null,
    ): void {
        $job = RefreshMaterializedView::dispatch(
            viewName: static::getMaterializedViewName(),
            concurrent: $concurrent,
            tenantId: $tenantId,
            connection: $connection,
        )->delay(now()->addSeconds($seconds));

        if ($queue !== null) {
            $job->onQueue($queue);
        }
    }

    public static function refreshNow(
        bool $concurrent = false,
        ?string $tenantId = null,
        ?string $connection = null,
    ): void {
        RefreshMaterializedView::dispatchSync(
            viewName: static::getMaterializedViewName(),
            concurrent: $concurrent,
            tenantId: $tenantId,
            connection: $connection,
        );
    }
}
