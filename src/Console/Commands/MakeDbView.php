<?php

namespace Splitstack\Rome\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class MakeDbView extends Command
{
    protected $signature = 'make:dbview {name?}';

    protected $description = 'Create a database view with migration, SQL file, and model';

    private string $migrationFilename;

    public function handle(): void
    {
        // Get the view name
        $name = $this->argument('name') ?: text(
            label: 'What is the name of the view?',
            placeholder: 'e.g., business_report',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 3 => 'The view name must be at least 3 characters.',
                ! preg_match('/^[a-z][a-z0-9_]*$/', $value) => 'The view name must only contain lowercase letters, digits, and underscores (must start with a letter).',
                default => null
            }
        );

        $name = Str::snake($name);

        if (strlen($name) < 3 || ! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $this->error('Invalid view name. Use only lowercase letters, digits, and underscores (must start with a letter).');

            return;
        }

        // Generate related names
        $viewName = $name.'_view';
        $modelName = Str::studly($viewName);
        $migrationName = 'create_'.$viewName;
        $sqlFileName = $name.'.sql';

        $this->info("Creating database view: {$viewName}");
        $this->info("Model name: {$modelName}");
        $this->info("Migration name: {$migrationName}");
        $this->info("SQL file: {$sqlFileName}");

        // Create directories if they don't exist
        File::ensureDirectoryExists(database_path('views'));
        File::ensureDirectoryExists(app_path('Models/Views'));

        // Create files
        $this->createSqlFile($sqlFileName, $viewName);
        $this->createMigration($migrationName, $sqlFileName, $viewName);
        $this->createModel($modelName, $viewName);

        $this->info('Database view created successfully!');
        $this->info('Files created:');
        $this->line("- database/views/{$sqlFileName}");
        $this->line("- {$this->migrationFilename}");
        $this->line("- app/Models/Views/{$modelName}.php");

        $this->info('Next steps:');
        $this->line('1. Add your SQL query to the generated SQL file');
        $this->line('2. Run: php artisan migrate');
        $this->line("3. Use your model: App\\Models\\Views\\{$modelName}");
    }

    private function createSqlFile(string $fileName, string $viewName): void
    {
        $sqlContent = <<<SQL
CREATE OR REPLACE VIEW {$viewName} AS

SELECT 
    -- Add your SELECT statement here
    id,
    created_at,
    updated_at
FROM your_table;
SQL;

        File::put(database_path("views/{$fileName}"), $sqlContent);
    }

    private function createMigration(string $migrationName, string $sqlFileName, string $viewName): void
    {
        $timestamp = date('Y_m_d_His');

        $content = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(file_get_contents(database_path('views/{$sqlFileName}')));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS {$viewName}');
    }
};
PHP;

        $filename = "{$timestamp}_{$migrationName}.php";
        $this->migrationFilename = "database/migrations/{$timestamp}_{$migrationName}.php";
        File::put(
            database_path("migrations/{$filename}"),
            $content
        );
    }

    private function createModel(string $modelName, string $viewName): void
    {
        $content = <<<PHP
<?php

namespace App\Models\Views;

use Splitstack\Rome\Models\ReadOnlyModel;

class {$modelName} extends ReadOnlyModel
{
    protected \$table = '{$viewName}';

    protected \$fillable = [];

    public \$timestamps = false;
}
PHP;

        File::put(app_path("Models/Views/{$modelName}.php"), $content);
    }
}
