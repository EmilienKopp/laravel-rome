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
        $identifier = $this->quoteIdentifier($viewName);

        return match ($this->driver) {
            'pgsql' => "DROP VIEW IF EXISTS {$identifier} CASCADE",
            default => "DROP VIEW IF EXISTS {$identifier}",
        };
    }

    public function dropMaterializedView(string $viewName): string
    {
        $this->assertMaterializedViewSupport();
        $identifier = $this->quoteIdentifier($viewName);

        return "DROP MATERIALIZED VIEW IF EXISTS {$identifier} CASCADE";
    }

    public function refreshMaterializedView(string $viewName, bool $concurrent = false): string
    {
        $this->assertMaterializedViewSupport();
        $identifier = $this->quoteIdentifier($viewName);

        return $concurrent
            ? "REFRESH MATERIALIZED VIEW CONCURRENTLY {$identifier}"
            : "REFRESH MATERIALIZED VIEW {$identifier}";
    }

    public function uniqueIndexSql(): string
    {
        return match ($this->driver) {
            'pgsql' => "SELECT COUNT(*) as count FROM pg_indexes WHERE tablename = ? AND indexdef LIKE '%UNIQUE%'",
            'mysql' => 'SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_name = ? AND non_unique = 0 AND table_schema = DATABASE()',
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

    private function quoteIdentifier(string $identifier): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', $identifier)) {
            throw new UnsupportedDriverException("Invalid view identifier '{$identifier}'.");
        }

        $parts = explode('.', $identifier);

        return match ($this->driver) {
            'mysql' => implode('.', array_map(static fn (string $part): string => "`{$part}`", $parts)),
            default => implode('.', array_map(static fn (string $part): string => "\"{$part}\"", $parts)),
        };
    }
}
