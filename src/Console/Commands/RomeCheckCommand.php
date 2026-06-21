<?php

namespace Splitstack\Rome\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RomeCheckCommand extends Command
{
    protected $signature = 'rome:check
        {--paths= : Comma-separated list of paths to analyse. Defaults to app_path().}';

    protected $description = 'Run PHPStan static analysis with Rome rules (ReadOnlyModel misuse detection)';

    public function handle(): int
    {
        $phpstan = base_path('vendor/bin/phpstan');

        if (! file_exists($phpstan)) {
            $this->error('PHPStan is not installed.');
            $this->line('  Run: <comment>composer require --dev phpstan/phpstan</comment>');

            return self::FAILURE;
        }

        $extensionNeon = base_path('vendor/splitstack/laravel-rome/extension.neon');

        if (! file_exists($extensionNeon)) {
            $this->error('Rome extension.neon not found at: '.$extensionNeon);
            $this->line('  Try re-running: <comment>composer install</comment>');

            return self::FAILURE;
        }

        $paths = $this->resolvePaths();

        if (empty($paths)) {
            $this->error('No valid directories to analyse. Check the --paths option.');

            return self::FAILURE;
        }

        $configFile = $this->writeTempConfig($extensionNeon, $paths);

        try {
            $this->line('Running Rome static analysis...');
            $this->newLine();

            $process = new Process([$phpstan, 'analyse', '--configuration='.$configFile, '--no-progress'], base_path());
            $process->run(fn ($type, $buffer) => $this->output->write($buffer));

            return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
        } finally {
            @unlink($configFile);
        }
    }

    /** @return string[] */
    private function resolvePaths(): array
    {
        if ($raw = $this->option('paths')) {
            $candidates = array_map('trim', explode(',', $raw));
        } else {
            $candidates = [app_path()];
        }

        $valid = array_filter($candidates, 'is_dir');

        if (count($valid) < count($candidates)) {
            foreach (array_diff($candidates, $valid) as $missing) {
                $this->warn('Path does not exist and will be skipped: '.$missing);
            }
        }

        return array_values($valid);
    }

    /** @param string[] $paths */
    private function writeTempConfig(string $extensionNeon, array $paths): string
    {
        $pathLines = implode("\n        - ", array_map(fn ($p) => "'".$p."'", $paths));

        $neon = <<<NEON
        includes:
            - {$extensionNeon}

        parameters:
            paths:
                - {$pathLines}
            customRulesetUsed: true
        NEON;

        $file = tempnam(sys_get_temp_dir(), 'rome-phpstan-').'.neon';
        file_put_contents($file, $neon);

        return $file;
    }
}
