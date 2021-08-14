<?php

namespace Pittacusw\Database;

use Illuminate\Console\Command;

class GitAddCommand extends Command {
 /**
  * The name and signature of the console command.
  *
  * @var string
  */
 protected $signature = 'git:add {message?}';

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
  $message = empty($this->argument('message')) ? 'Backup' : $this->argument('message');
  $this->line(exec("git add ."));
  $this->line(exec('git commit -m "' . $message . '"'));
  $this->line(exec('git push'));
 }
}
