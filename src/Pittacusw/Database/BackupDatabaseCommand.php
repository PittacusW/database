<?php

namespace Pittacusw\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupDatabaseCommand extends Command {
 /**
  * The name and signature of the console command.
  *
  * @var string
  */
 protected $signature = 'db:backup';

 /**
  * The console command description.
  *
  * @var string
  */
 protected $description = 'Backups the database';

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
  $db   = env('DB_DATABASE');
  $pass = env('DB_PASSWORD');
  if (!is_dir('database/sql')) {
   mkdir('database/sql');
  }
  collect(DB::select('SHOW TABLES'))->each(function($table) use ($db, $user, $host, $pass) {
   $name    = 'Tables_in_' . $db;
   $file    = "database/sql/" . $table->$name . ".sql.gz";
   $command = sprintf('mysqldump -h %s -u %s -p\'%s\' %s %s | gzip -c > %s', $host, $user, $pass, $db, $table->$name, $file);
   $this->line('Backing up ' . $table->$name);
   $this->line(exec($command));
   $this->line('Back up of ' . $table->$name . ' completed');
  });
 }
}
