<?php

namespace Splitstack\Rome\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Splitstack\Rome\Exceptions\UnsupportedDriverException;

class MaterializedViewRefresher
{
    private ViewDialect $dialect;

    public function __construct(private readonly string $connection)
    {
        $this->dialect = ViewDialect::fromConnection($connection);
    }

    public function refresh(string $viewName, bool $concurrent = false): void
    {
        if (! $this->dialect->supportsMaterializedViews()) {
            throw new UnsupportedDriverException(
                "Materialized views are not supported by driver '{$this->dialect->driver()}'."
            );
        }

        if ($concurrent && ! $this->hasUniqueIndex($viewName)) {
            Log::warning("Materialized view '{$viewName}' has no unique index — falling back to blocking refresh.");
            $concurrent = false;
        }

        // unprepared() avoids conflicts with any wrapping transaction
        DB::connection($this->connection)->unprepared(
            $this->dialect->refreshMaterializedView($viewName, $concurrent)
        );
    }

    public function hasUniqueIndex(string $viewName): bool
    {
        $result = DB::connection($this->connection)->selectOne(
            $this->dialect->uniqueIndexSql(),
            [$viewName]
        );

        return $result->count > 0;
    }
}
