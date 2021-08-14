<?php

namespace Pittacusw\Database;

use Illuminate\Support\ServiceProvider;
use App\Console\Commands\BackupDatabaseCommand;

class DatabaseServiceProvider extends ServiceProvider {
 /**
  * Indicates if loading of the provider is deferred.
  *
  * @var bool
  */
 protected $defer = FALSE;

 /**
  * Bootstrap the application events.
  *
  * @return void
  */
 public function boot() {
  require base_path() . '/vendor/autoload.php';
 }

 /**
  * Register the service provider.
  *
  * @return void
  */
 public function register() {
  $this->registerResources();

  $this->app->singleton('command.db.backup', function($app) {
   return new BackupDatabaseCommand;
  });

  $this->commands('command.db.backup');
 }
}