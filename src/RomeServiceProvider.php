<?php

namespace Splitstack\Rome;

use Illuminate\Support\ServiceProvider;

class RomeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rome.php', 'rome');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/rome.php' => config_path('rome.php'),
            ], 'rome-config');
        }
    }
}
