<?php

use Splitstack\Rome\Database\ViewDialect;
use Splitstack\Rome\Exceptions\UnsupportedDriverException;

it('returns the driver name', function () {
    expect((new ViewDialect('pgsql'))->driver())->toBe('pgsql');
});

it('supports materialized views only for pgsql', function (string $driver, bool $expected) {
    expect((new ViewDialect($driver))->supportsMaterializedViews())->toBe($expected);
})->with([
    ['pgsql', true],
    ['mysql', false],
    ['sqlite', false],
]);

describe('dropView', function () {
    it('adds CASCADE for pgsql', function () {
        expect((new ViewDialect('pgsql'))->dropView('reports'))
            ->toBe('DROP VIEW IF EXISTS reports CASCADE');
    });

    it('generates simple DROP for other drivers', function () {
        expect((new ViewDialect('mysql'))->dropView('reports'))
            ->toBe('DROP VIEW IF EXISTS reports');
    });
});

describe('dropMaterializedView', function () {
    it('generates DROP MATERIALIZED VIEW with CASCADE for pgsql', function () {
        expect((new ViewDialect('pgsql'))->dropMaterializedView('reports'))
            ->toBe('DROP MATERIALIZED VIEW IF EXISTS reports CASCADE');
    });

    it('throws UnsupportedDriverException for non-pgsql', function () {
        (new ViewDialect('mysql'))->dropMaterializedView('reports');
    })->throws(UnsupportedDriverException::class);
});

describe('refreshMaterializedView', function () {
    it('generates blocking REFRESH for pgsql', function () {
        expect((new ViewDialect('pgsql'))->refreshMaterializedView('reports'))
            ->toBe('REFRESH MATERIALIZED VIEW reports');
    });

    it('generates CONCURRENT REFRESH when requested', function () {
        expect((new ViewDialect('pgsql'))->refreshMaterializedView('reports', concurrent: true))
            ->toBe('REFRESH MATERIALIZED VIEW CONCURRENTLY reports');
    });

    it('throws UnsupportedDriverException for non-pgsql', function () {
        (new ViewDialect('sqlite'))->refreshMaterializedView('reports');
    })->throws(UnsupportedDriverException::class);
});

describe('uniqueIndexSql', function () {
    it('queries pg_indexes for pgsql', function () {
        expect((new ViewDialect('pgsql'))->uniqueIndexSql())->toContain('pg_indexes');
    });

    it('queries information_schema for mysql', function () {
        expect((new ViewDialect('mysql'))->uniqueIndexSql())->toContain('information_schema');
    });

    it('throws UnsupportedDriverException for sqlite', function () {
        (new ViewDialect('sqlite'))->uniqueIndexSql();
    })->throws(UnsupportedDriverException::class);
});
