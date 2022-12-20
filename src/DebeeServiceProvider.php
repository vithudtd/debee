<?php
namespace Apptimuscore\Debee;

use Apptimuscore\Debee\Console\Commands\TestVersion;
use Illuminate\Support\ServiceProvider;

class DebeeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestVersion::class
            ]);
        }
    }

    public function register()
    {
        # code...
    }
}
