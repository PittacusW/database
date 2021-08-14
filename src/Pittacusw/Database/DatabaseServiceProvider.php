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
  $this->app->singleton('command.db.restore', function($app) {
   return new \Pittacusw\Database\RestoreDatabaseCommand;
  });
  $this->commands('command.db.restore');

  $this->app->singleton('command.db.backup', function($app) {
   return new \Pittacusw\Database\BackupDatabaseCommand;
  });
  $this->commands('command.db.backup');

  $this->app->singleton('command.db.iseed', function($app) {
   return new \Pittacusw\Database\IseedDatabaseCommand;
  });
  $this->commands('command.db.iseed');

  $this->app->singleton('command.git.add', function($app) {
   return new \Pittacusw\Database\GitAddCommand;
  });
  $this->commands('command.git.add');
 }
}