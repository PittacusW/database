<?php

namespace Pittacusw\Database;

use Illuminate\Console\Command;

class GitAddCommandCommand extends Command {
 /**
  * The name and signature of the console command.
  *
  * @var string
  */
 protected $signature = 'git:add';

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
  echo exec("git add .");
  echo exec('git commit -m "Backup"');
  echo exec('git push');
 }
}
