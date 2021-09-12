<?php

namespace Pittacusw\Database;

use Illuminate\Support\ServiceProvider;

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
  $this->app->booting(function() {
   $loader = \Illuminate\Foundation\AliasLoader::getInstance();
   $loader->alias('Iseed', 'PittacusW\Database\Facades\Iseed');
  });

  $this->app->singleton('command.db.restore', function($app) {
   return new RestoreDatabaseCommand;
  });
  $this->commands('command.db.restore');

  $this->app->singleton('command.db.backup', function($app) {
   return new BackupDatabaseCommand;
  });
  $this->commands('command.db.backup');

  $this->app->singleton('command.db.iseed', function($app) {
   return new IseedDatabaseCommand;
  });
  $this->commands('command.db.iseed');

  $this->app->singleton('command.git.add', function($app) {
   return new GitAddCommand;
  });
  $this->commands('command.git.add');

  $this->app->singleton('iseed', function($app) {
   return new Iseed;
  });

  $this->app->singleton('command.iseed', function($app) {
   return new IseedCommand;
  });
  $this->commands('command.iseed');
 }

 public function provides() {
  return ['iseed'];
 }
}