<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RestoreDatabaseCommand extends Command {
 /**
  * The name and signature of the console command.
  *
  * @var string
  */
 protected $signature = 'db:restore';

 /**
  * The console command description.
  *
  * @var string
  */
 protected $description = 'Restores the database';

 /**
  * Create a new command instance.
  *
  * @return void
  */
 public function __construct() {
  parent::__construct();
 }

 /**
  * Execute the console command.
  *
  * @return int
  */
 public function handle() {
  $host = env('DB_HOST');
  $user = env('DB_USERNAME');
  $pass = env('DB_PASSWORD');
  $db   = env('DB_DATABASE');
  foreach (config('locales') as $locale) {
   $file = "database/sql/{$locale['words']}.sql.gz";
   if (File::exists($file)) {
    echo exec("mysql -u {$user} -p\"{$pass}\" -h {$host} {$db} < {$file}");
   }
   if (File::exists($file)) {
    $file = "database/sql/{$locale['previous_queries']}.sql.gz";
    echo exec("mysql -u {$user} -p\"{$pass}\" -h {$host} {$db} < {$file}");
   }
   $file = "database/sql/{$locale['sitemap']}.sql.gz";
   if (File::exists($file)) {
    echo exec("mysql -u {$user} -p\"{$pass}\" -h {$host} {$db} < {$file}");
   }
  }
 }
}
