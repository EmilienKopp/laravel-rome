<?php

namespace Splitstack\Rome\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Splitstack\Rome\Models\ReadOnlyModel;

/**
 * Flags ReadOnlyModel subclasses where a column computed in the view's SQL
 * (i.e. appears as the *target* of an "AS" alias on a non-trivial expression)
 * shares a name with a fillable column on $proxyTo, but isn't listed in $exclude.
 *
 * This is intentionally a heuristic, not a full SQL parser. False positives are
 * acceptable; false negatives defeat the purpose.
 */
class ViewDefinitionsMightCollide implements Rule
{
    public function __construct(
        private string $rootDir = '',
        private string $dbViewsPath = '',
    ) {}

    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    /**
     * @param  Node\Stmt\Class_  $node
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        if (! $node->namespacedName instanceof Node\Name) {
            return []; // anonymous class
        }
        $className = (string) $node->namespacedName;
        if (! is_subclass_of($className, ReadOnlyModel::class)) {
            return [];
        }

        // --- Pull static property values via reflection ---
        $reflection = new \ReflectionClass($className);

        $proxyTo = $this->readStaticProperty($reflection, 'proxyTo');
        if (! $proxyTo || ! class_exists($proxyTo)) {
            return []; // proxying not configured on this model, nothing to check
        }

        $exclude = $this->readStaticProperty($reflection, 'exclude') ?? [];

        // --- Get the proxied model's fillable columns ---
        // Use reflection to avoid requiring a live service container during static analysis.
        $proxiedReflection = new \ReflectionClass($proxyTo);
        $proxiedFillable = $proxiedReflection->getDefaultProperties()['fillable'] ?? [];

        if (empty($proxiedFillable)) {
            return [];
        }

        // --- Locate and read the view's SQL file ---
        $table = $this->readStaticPropertyInstance($className, 'table');
        if (! $table) {
            return [];
        }

        $sqlPath = $this->resolveSqlPath($table);
        if (! $sqlPath || ! file_exists($sqlPath)) {
            return [];
        }

        $sql = file_get_contents($sqlPath);

        // --- A) Check for computed columns colliding with $proxyTo's fillable ---
        $computedColumns = $this->findComputedAliases($sql, $proxiedFillable);

        foreach ($computedColumns as $column) {
            if (in_array($column, $exclude, true)) {
                continue; // already excluded, safe
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Column "%s" is computed in the view SQL for "%s" and also exists as a fillable '.
                'attribute on proxied model "%s", but is not listed in $exclude. '.
                'This may cause proxied()/underlying(false) to hydrate a computed value into a '.
                'writable model. Add "%s" to $exclude on %s, or rename the SQL alias.',
                $column,
                $table,
                $proxyTo,
                $column,
                $className
            ))
                ->identifier('rome.proxyComputedColumnCollision')
                ->build();
        }

        // --- B) Check for columns sourced from a joined table colliding with $proxyTo's fillable ---
        $proxiedTable = $this->readStaticPropertyInstance($proxyTo, 'table') ?? '';
        $primaryAlias = $this->resolvePrimaryTableAlias($sql, $proxiedTable);
        $foreignColumns = $this->findForeignTableColumns($sql, $proxiedFillable, $primaryAlias);

        foreach ($foreignColumns as $column) {
            if (in_array($column, $exclude, true)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Column "%s" in the view SQL for "%s" is sourced from a joined table, not from '.
                'the primary table of proxied model "%s". This may cause proxied()/underlying(false) '.
                'to write a stale or incorrect value. Add "%s" to $exclude on %s, or use a distinct alias.',
                $column,
                $table,
                $proxyTo,
                $column,
                $className
            ))
                ->identifier('rome.foreignTableColumnCollision')
                ->build();
        }

        // --- C) Check that every excluded column actually appears in the SQL ---
        // (catches stale entries — e.g. the SQL alias was renamed but $exclude wasn't updated)
        $flaggedColumns = array_merge($computedColumns, $foreignColumns);
        foreach ($exclude as $excludedColumn) {
            if (! in_array($excludedColumn, $flaggedColumns, true)
                && ! str_contains($sql, $excludedColumn)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    '"%s" is listed in $exclude on %s but does not appear anywhere in the view SQL "%s". '.
                    'This entry may be stale.',
                    $excludedColumn,
                    $className,
                    $table
                ))
                    ->identifier('rome.staleExcludeEntry')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * Regex-based heuristic: find column names that appear as the target of an
     * "AS <column>" alias where the left-hand side looks computed (contains an
     * operator, a function call, or CASE) rather than a bare passthrough column.
     *
     * @param  string[]  $candidateColumns
     * @return string[]
     */
    private function findComputedAliases(string $sql, array $candidateColumns): array
    {
        $found = [];

        foreach ($candidateColumns as $column) {
            $quoted = preg_quote($column, '/');

            // left side has an operator, a function call "word(", or CASE,
            // ending in "AS <optional table/alias prefix><column>"
            // (column itself optionally quoted/backticked; prefix is bare or quoted too)
            $pattern = '/(?:[\+\-\*\/]|\bCASE\b|\w+\s*\().*?\bAS\s+'.
                '(?:["`]?\w+["`]?\.)?'.   // optional "table." or "alias." or `alias`.
                '["`]?'.$quoted.'["`]?/is';

            if (preg_match($pattern, $sql)) {
                $found[] = $column;
            }
        }

        return $found;
    }

    /**
     * Finds candidate columns that appear in the SELECT list sourced from a table/alias
     * OTHER than the view's "primary" table — either explicitly aliased to that name,
     * or implicitly via "alias.column" with no AS clause at all (column name = alias).
     *
     * This catches joins leaking a same-named column from an unrelated table into a
     * column name that collides with $proxyTo's fillable.
     *
     * @param  string[]  $candidateColumns
     * @param  string  $primaryTableOrAlias  the FROM table/alias representing $proxyTo's own table
     * @return string[]
     */
    private function findForeignTableColumns(string $sql, array $candidateColumns, string $primaryTableOrAlias): array
    {
        $found = [];

        // Extract the SELECT clause only (up to FROM), to avoid false matches in WHERE/JOIN ON clauses
        if (! preg_match('/SELECT\s+(.*?)\s+FROM\s/is', $sql, $selectMatch)) {
            return $found;
        }
        $selectList = $selectMatch[1];

        // Split on top-level commas (naive — doesn't handle commas inside function calls,
        // good enough for typical view SELECT lists; revisit if you hit false splits)
        $items = preg_split('/,(?![^(]*\))/', $selectList);

        foreach ($items as $item) {
            $item = trim($item);

            // Case 1: "alias.column AS alias_for_column" — explicit alias
            if (preg_match('/^["`]?(\w+)["`]?\.["`]?(\w+)["`]?\s+AS\s+["`]?(\w+)["`]?$/i', $item, $m)) {
                [, $sourceAlias, , $aliasedAs] = $m;
                if ($sourceAlias !== $primaryTableOrAlias && in_array($aliasedAs, $candidateColumns, true)) {
                    $found[] = $aliasedAs;
                }

                continue;
            }

            // Case 2: "alias.column" with no AS — the column's own name IS the output name
            if (preg_match('/^["`]?(\w+)["`]?\.["`]?(\w+)["`]?$/i', $item, $m)) {
                [, $sourceAlias, $sourceColumn] = $m;
                if ($sourceAlias !== $primaryTableOrAlias && in_array($sourceColumn, $candidateColumns, true)) {
                    $found[] = $sourceColumn;
                }
            }
        }

        return array_unique($found);
    }

    private function readStaticProperty(\ReflectionClass $reflection, string $property): mixed
    {
        if (! $reflection->hasProperty($property)) {
            return null;
        }

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->isStatic() ? $prop->getValue() : null;
    }

    private function readStaticPropertyInstance(string $className, string $property): mixed
    {
        $reflection = new \ReflectionClass($className);
        if (! $reflection->hasProperty($property)) {
            return null;
        }

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        // $table is an instance property with a default, not static —
        // read it off the class's default property values instead of instantiating.
        $defaults = $reflection->getDefaultProperties();

        return $defaults[$property] ?? null;
    }

    /**
     * Returns the alias (or the table name itself) used for $tableName in the FROM clause.
     * e.g. "FROM users u" → "u"; "FROM users" → "users"
     */
    private function resolvePrimaryTableAlias(string $sql, string $tableName): string
    {
        $quoted = preg_quote($tableName, '/');
        $sqlKeywords = '/^(JOIN|LEFT|RIGHT|INNER|OUTER|CROSS|WHERE|ON|GROUP|ORDER|HAVING|LIMIT|UNION|AS|SET)$/i';
        if (preg_match('/\bFROM\s+`?"?' . $quoted . '`?"?\s+(?:AS\s+)?(\w+)/i', $sql, $m)
            && ! preg_match($sqlKeywords, $m[1])) {
            return $m[1];
        }

        return $tableName;
    }

    private function resolveSqlPath(string $table): ?string
    {
        $base = $this->dbViewsPath !== '' ? $this->dbViewsPath : $this->readConfigViewsPath();
        if ($base === '') {
            return null;
        }

        $path = rtrim($base, '/').'/'.$table.'.sql';

        return file_exists($path) ? $path : null;
    }

    private function readConfigViewsPath(): string
    {
        $configFile = rtrim($this->rootDir, '/').'/config/rome.php';
        if (! file_exists($configFile)) {
            return '';
        }

        try {
            $config = include $configFile;

            return is_array($config) ? (string) ($config['db_views_path'] ?? '') : '';
        } catch (\Throwable) {
            // database_path() or other Laravel helpers unavailable; fall back to convention.
            return rtrim($this->rootDir, '/').'/database/views';
        }
    }
}
