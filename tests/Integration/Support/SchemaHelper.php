<?php

namespace Splitstack\Rome\Tests\Integration\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchemaHelper
{
    public function __construct(private readonly string $connection) {}

    public function createItemsTableAndView(): void
    {
        $this->dropItemsTableAndView();

        Schema::connection($this->connection)->create('rome_integration_items', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('status')->default('active');
        });

        $driver = DB::connection($this->connection)->getDriverName();

        if ($driver === 'pgsql') {
            DB::connection($this->connection)->statement(<<<'SQL'
                CREATE OR REPLACE VIEW rome_integration_items_view AS
                SELECT
                    id,
                    name,
                    status,
                    name || ' [' || status || ']' AS display_name
                FROM rome_integration_items
            SQL);
        } else {
            DB::connection($this->connection)->statement(<<<'SQL'
                CREATE OR REPLACE VIEW `rome_integration_items_view` AS
                SELECT
                    `id`,
                    `name`,
                    `status`,
                    CONCAT(`name`, ' [', `status`, ']') AS `display_name`
                FROM `rome_integration_items`
            SQL);
        }
    }

    public function dropItemsTableAndView(): void
    {
        $driver = DB::connection($this->connection)->getDriverName();

        if ($driver === 'pgsql') {
            DB::connection($this->connection)->statement(
                'DROP VIEW IF EXISTS rome_integration_items_view CASCADE'
            );
        } else {
            DB::connection($this->connection)->statement(
                'DROP VIEW IF EXISTS `rome_integration_items_view`'
            );
        }

        Schema::connection($this->connection)->dropIfExists('rome_integration_items');
    }

    public function seedItem(int $id, string $name, string $status = 'active'): void
    {
        DB::connection($this->connection)->table('rome_integration_items')->insert([
            'id'     => $id,
            'name'   => $name,
            'status' => $status,
        ]);
    }

    public function createMaterializedView(): void
    {
        DB::connection($this->connection)->statement(
            'DROP MATERIALIZED VIEW IF EXISTS rome_integration_items_matview CASCADE'
        );

        DB::connection($this->connection)->statement(<<<'SQL'
            CREATE MATERIALIZED VIEW rome_integration_items_matview AS
            SELECT
                id,
                name,
                status,
                name || ' [' || status || ']' AS display_name
            FROM rome_integration_items
        SQL);
    }

    public function dropMaterializedView(): void
    {
        DB::connection($this->connection)->statement(
            'DROP MATERIALIZED VIEW IF EXISTS rome_integration_items_matview CASCADE'
        );
    }

    public function addUniqueIndexToMatview(): void
    {
        DB::connection($this->connection)->statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS
                rome_integration_items_matview_id_unique
            ON rome_integration_items_matview (id)
        SQL);
    }
}
