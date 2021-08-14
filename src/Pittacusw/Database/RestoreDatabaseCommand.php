<?php

namespace Pittacusw\Database;

use Illuminate\Console\Command;

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
  $path = "database/sql";
  if ($handle = opendir($path)) {
   while (FALSE !== ($file = readdir($handle))) {
    if ('.' === $file) {
     continue;
    }
    if ('..' === $file) {
     continue;
    }
    $sql     = "database/sql/" . $file;
    $command = sprintf('mysql -h %s -u %s -p\'%s\' %s < %s', $host, $user, $pass, $db, $sql);
    $this->line('Restoring ' . str_replace('.sql.gz', '', $file));
    $this->line(exec($command));
    $this->line('Restoring of ' . str_replace('.sql.gz', '', $file) . ' completed');
   }
   closedir($handle);
  }
 }
}
