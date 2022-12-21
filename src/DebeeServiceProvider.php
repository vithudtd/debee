<?php
namespace Apptimus\Debee;

use Apptimus\Debee\Console\Commands\CheckVersion;
use Apptimus\Debee\Console\Commands\DebeeConnect;
use Apptimus\Debee\Console\Commands\UpdateDB;
use Apptimus\Debee\Console\Commands\User;
use Apptimus\Debee\Console\Commands\Project;
use Apptimus\Debee\Console\Commands\RunQuery;
use Illuminate\Support\ServiceProvider;

class DebeeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/config/config.php', 'debee');
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckVersion::class,
                UpdateDB::class,
                DebeeConnect::class,
                User::class,
                Project::class,
                RunQuery::class
            ]);
        }
    }

    public function register()
    {
        # code...
    }
}
