<?php
namespace Deploy\ServiceProvider;

use Illuminate\Support\ServiceProvider;
use Artisan;
use Deploy\Worker\Commands\ListenCommand;
use Deploy\Worker\Commands\WorkerCommand;
use Deploy\Worker\Commands\SiteClearCommand;

class Worker extends ServiceProvider
{
    public function register()
    {
        $this->registerCommands();
    }

    public function registerCommands()
    {
        $this->app->bindShared('command.worker.listen', function ($app) {
            return new ListenCommand;
        });
        $this->commands('command.worker.listen');

        $this->app->bindShared('command.worker.job', function ($app) {
            return new WorkerCommand;
        });
        $this->commands('command.worker.job');

        $this->app->bindShared('command.site.clear', function ($app) {
            return new SiteClearCommand;
        });
        $this->commands('command.site.clear');
    }

    public function boot()
    {
    }
}
