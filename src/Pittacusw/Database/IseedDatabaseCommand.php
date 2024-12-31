<?php

namespace Pittacusw\Database;

use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IseedDatabaseCommand extends Command {
 /**
  * The name and signature of the console command.
  *
  * @var string
  */
 protected $signature = 'db:iseed {table?}';

 /**
  * The console command description.
  *
  * @var string
  */
 protected $description = 'Create a seed for all tables or a specified table';

 /**
  * Tables to be ignored
  *
  * @var string
  */
 protected $ignore = [
  'migrations',
  'password_resets',
  'failed_jobs',
 'github_webhook_calls',
 'jobs'
 ];

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
  $table  = $this->argument('table');
  $tables = with(empty($table) ? (collect(DB::select('SHOW TABLES'))
   ->pluck('Tables_in_' . env('DB_DATABASE', 'homestead'))
   ->diff(collect($this->ignore))) : collect(Arr::wrap($table)));
  if ($tables->count()) {
   $this->call('iseed', [
    'tables'     => $tables->implode(','),
    '--force'    => TRUE,
    '--clean'    => is_null($table),
    '--dumpauto' => FALSE,
               '--chunksize'=>100
   ]);
  }
 }
}
