<?php

namespace Splitstack\Rome\Database;

use Illuminate\Support\Facades\DB;
use Splitstack\Rome\Exceptions\UnsupportedDriverException;

class ViewDialect
{
    public function __construct(private readonly string $driver) {}

    public static function fromConnection(string $connection): self
    {
        return new self(DB::connection($connection)->getDriverName());
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function supportsMaterializedViews(): bool
    {
        return $this->driver === 'pgsql';
    }

    public function dropView(string $viewName): string
    {
        return match ($this->driver) {
            'pgsql' => "DROP VIEW IF EXISTS {$viewName} CASCADE",
            default => "DROP VIEW IF EXISTS {$viewName}",
        };
    }

    public function dropMaterializedView(string $viewName): string
    {
        $this->assertMaterializedViewSupport();

        return "DROP MATERIALIZED VIEW IF EXISTS {$viewName} CASCADE";
    }

    public function refreshMaterializedView(string $viewName, bool $concurrent = false): string
    {
        $this->assertMaterializedViewSupport();

        return $concurrent
            ? "REFRESH MATERIALIZED VIEW CONCURRENTLY {$viewName}"
            : "REFRESH MATERIALIZED VIEW {$viewName}";
    }

    public function uniqueIndexSql(): string
    {
        return match ($this->driver) {
            'pgsql' => "SELECT COUNT(*) as count FROM pg_indexes WHERE tablename = ? AND indexdef LIKE '%UNIQUE%'",
            'mysql' => "SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_name = ? AND non_unique = 0 AND table_schema = DATABASE()",
            default => throw new UnsupportedDriverException("Unique index check is not implemented for driver '{$this->driver}'."),
        };
    }

    private function assertMaterializedViewSupport(): void
    {
        if (! $this->supportsMaterializedViews()) {
            throw new UnsupportedDriverException(
                "Materialized views are not supported by driver '{$this->driver}'."
            );
        }
    }
}
