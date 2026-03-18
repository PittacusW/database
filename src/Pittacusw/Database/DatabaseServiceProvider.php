<?php

namespace Pittacusw\Database;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Iseed::class, function ($app) {
            $files = $app->make(Filesystem::class);

            return new Iseed($files, new Composer($files, $app->basePath()));
        });

        $this->app->alias(Iseed::class, 'iseed');
    }

    public function boot()
    {
        $this->commands([
            BackupDatabaseCommand::class,
            IseedCommand::class,
            IseedDatabaseCommand::class,
            RestoreDatabaseCommand::class,
        ]);
    }
}
