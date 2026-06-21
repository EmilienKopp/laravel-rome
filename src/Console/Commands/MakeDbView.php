<?php

namespace Splitstack\Rome\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeDbView extends GeneratorCommand
{
    protected $name = 'make:dbview';

    protected $description = 'Create a database view with migration, SQL file, and model';

    protected $type = 'View model';

    private string $migrationFilename;

    private string $dbViewName;

    private array $sourceColumns = [];

    public function handle(): void
    {
        $rawName = $this->argument('name') ?: text(
            label: 'What is the name of the view?',
            placeholder: 'e.g., business_report',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 3 => 'The view name must be at least 3 characters.',
                ! preg_match('/^[a-z][a-z0-9_]*$/', $value) => 'The view name must only contain lowercase letters, digits, and underscores (must start with a letter).',
                default => null
            }
        );

        $name = Str::snake($rawName);

        if (strlen($name) < 3 || ! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $this->error('Invalid view name. Use only lowercase letters, digits, and underscores (must start with a letter).');

            return;
        }

        $this->dbViewName = $name.'_view';
        $modelName = Str::studly($this->dbViewName);
        $migrationName = 'create_'.$this->dbViewName;
        $sqlFileName = $name.'.sql';

        $this->sourceColumns = $this->resolveSourceColumns();

        $this->files->ensureDirectoryExists(database_path('views'));

        // Create the migration first so we can extract its timestamp and stamp the SQL file to match.
        // This ensures each SQL snapshot is versioned alongside the migration that uses it,
        // so running migrations from scratch always picks up the correct SQL at each step.
        $migrationPath = $this->createMigration($migrationName);
        $timestamp = $this->extractMigrationTimestamp($migrationPath);
        $sqlFileName = $timestamp.'_'.$name.'.sql';

        $this->updateMigrationSqlReference($migrationPath, $sqlFileName);
        $this->createSqlFile($sqlFileName);

        // Use GeneratorCommand machinery for the model file
        $qualifiedClass = $this->qualifyClass($modelName);
        $path = $this->getPath($qualifiedClass);

        if ($this->alreadyExists($modelName)) {
            $this->components->error("{$this->type} [{$modelName}] already exists.");

            return;
        }

        $this->makeDirectory($path);
        $this->files->put($path, $this->sortImports($this->buildClass($qualifiedClass)));

        $this->components->info("{$this->type} [{$path}] created successfully.");
        $this->line("- database/views/{$sqlFileName}");
        $this->line("- {$this->migrationFilename}");
        $this->info('Next steps:');
        $this->line('1. Edit the SQL file with your query');
        $this->line('2. Run: php artisan migrate');
        $this->line("3. Use: {$qualifiedClass}");
    }

    protected function getStub(): string
    {
        return __DIR__.'/stubs/dbview.model.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $path = config('rome.readonly_model_path', 'Models/Views');

        return $rootNamespace.'\\'.str_replace('/', '\\', $path);
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $fillable = empty($this->sourceColumns)
            ? '[]'
            : "[\n        '".implode("',\n        '", $this->sourceColumns)."',\n    ]";

        return str_replace(
            ['{{ table }}', '{{table}}', '{{ fillable }}', '{{fillable}}'],
            [$this->dbViewName, $this->dbViewName, $fillable, $fillable],
            $stub
        );
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::OPTIONAL, 'The snake_case name for the database view (without _view suffix)'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['model', null, InputOption::VALUE_OPTIONAL, 'Model class to seed the SELECT from (skips prompt)'],
        ];
    }

    /** @return string[] */
    private function resolveSourceColumns(): array
    {
        $modelClass = $this->option('model');

        if (! $modelClass) {
            $models = $this->discoverModels();

            if (empty($models)) {
                return [];
            }

            $choice = select(
                label: 'Seed SELECT from a model? (optional)',
                options: array_merge(['(none)'], $models),
                default: '(none)',
            );

            if ($choice === '(none)') {
                return [];
            }

            $modelClass = $choice;
        }

        if (! class_exists($modelClass)) {
            $this->warn("Model class [{$modelClass}] not found — skipping column seeding.");

            return [];
        }

        return (new $modelClass)->getFillable();
    }

    /** @return string[] */
    private function discoverModels(): array
    {
        $extraPaths = config('rome.model_scan_paths', []);
        $dirs = array_merge(['Models'], (array) $extraPaths);
        $models = [];

        foreach ($dirs as $dir) {
            $path = app_path($dir);

            if (! $this->files->isDirectory($path)) {
                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = Str::of($file->getRealPath())
                    ->after(app_path().DIRECTORY_SEPARATOR)
                    ->replaceLast('.php', '')
                    ->replace(DIRECTORY_SEPARATOR, '\\');

                $class = $this->rootNamespace().$relative;

                if (class_exists($class) && is_subclass_of($class, Model::class)) {
                    $models[] = $class;
                }
            }
        }

        sort($models);

        return $models;
    }

    private function createSqlFile(string $fileName): void
    {
        $selectLines = empty($this->sourceColumns)
            ? "    -- Add your SELECT statement here\n    id,\n    created_at,\n    updated_at"
            : '    '.implode(",\n    ", $this->sourceColumns);

        $sqlContent = "CREATE OR REPLACE VIEW {$this->dbViewName} AS\n\nSELECT\n{$selectLines}\nFROM your_table;";

        $this->files->put(database_path("views/{$fileName}"), $sqlContent);
    }

    private function createMigration(string $migrationName): string
    {
        $path = $this->laravel['migration.creator']->create(
            $migrationName,
            $this->laravel->databasePath('migrations')
        );

        $this->migrationFilename = 'database/migrations/'.basename($path);

        return $path;
    }

    private function extractMigrationTimestamp(string $migrationPath): string
    {
        // Migration filenames are: 2024_01_15_123456_create_foo_view.php
        preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', basename($migrationPath), $m);

        return $m[1] ?? date('Y_m_d_His');
    }

    private function updateMigrationSqlReference(string $migrationPath, string $sqlFileName): void
    {
        $viewName = $this->dbViewName;

        $this->files->put($migrationPath, <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(file_get_contents(database_path('views/{$sqlFileName}')));
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS {$viewName}');
    }
};
PHP);
    }
}
