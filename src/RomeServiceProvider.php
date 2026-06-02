<?php

namespace Splitstack\Rome;

use Illuminate\Support\ServiceProvider;
use Splitstack\Rome\Console\Commands\MakeDbView;
use Splitstack\Rome\Console\Commands\RegenerateViewCommand;

class RomeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rome.php', 'rome');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeDbView::class,
                RegenerateViewCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/rome.php' => config_path('rome.php'),
            ], 'rome-config');
        }
    }
}
