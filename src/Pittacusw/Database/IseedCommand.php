<?php

namespace Pittacusw\Database;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class IseedCommand extends Command {
 /**
  * The console command name.
  *
  * @var string
  */
 protected $name = 'iseed';

 /**
  * The console command description.
  *
  * @var string
  */
 protected $description = 'Generate seed file from table';

 /**
  * Create a new command instance.
  *
  * @return \Orangehill\Iseed\IseedCommand
  */
 public function __construct() {
  parent::__construct();
 }

 /**
  * Execute the console command.
  *
  * @return void
  */
 public function handle() {
  return $this->fire();
 }

 /**
  * Execute the console command (for <= 5.4).
  *
  * @return void
  */
 public function fire() {
  // if clean option is checked empty iSeed template in DatabaseSeeder.php
  if ($this->option('clean')) {
   app('iseed')->cleanSection();
  }

  $tables        = explode(",", $this->argument('tables'));
  $max           = intval($this->option('max'));
  $chunkSize     = intval($this->option('chunksize'));
  $exclude       = explode(",", $this->option('exclude'));
  $prerunEvents  = explode(",", $this->option('prerun'));
  $postrunEvents = explode(",", $this->option('postrun'));
  $dumpAuto      = intval($this->option('dumpauto'));
  $indexed       = !$this->option('noindex');
  $orderBy       = $this->option('orderby');
  $direction     = $this->option('direction');
  $prefix        = $this->option('classnameprefix');
  $suffix        = $this->option('classnamesuffix');

  if ($max < 1) {
   $max = NULL;
  }

  if ($chunkSize < 1) {
   $chunkSize = NULL;
  }

  $tableIncrement = 0;
  foreach ($tables as $table) {
   $table       = trim($table);
   $prerunEvent = NULL;
   if (isset($prerunEvents[$tableIncrement])) {
    $prerunEvent = trim($prerunEvents[$tableIncrement]);
   }
   $postrunEvent = NULL;
   if (isset($postrunEvents[$tableIncrement])) {
    $postrunEvent = trim($postrunEvents[$tableIncrement]);
   }
   $tableIncrement ++;

   // generate file and class name based on name of the table
   [
    $fileName,
    $className
   ] = $this->generateFileName($table, $prefix, $suffix);

   // if file does not exist or force option is turned on generate seeder
   if (!\File::exists($fileName) || $this->option('force')) {
    $this->printResult(
     app('iseed')->generateSeed(
      $table,
      $prefix,
      $suffix,
      $this->option('database'),
      $max,
      $chunkSize,
      $exclude,
      $prerunEvent,
      $postrunEvent,
      $dumpAuto,
      $indexed,
      $orderBy,
      $direction
     ),
     $table
    );
    continue;
   }

   if ($this->confirm('File ' . $className . ' already exist. Do you wish to override it? [yes|no]')) {
    // if user said yes overwrite old seeder
    $this->printResult(
     app('iseed')->generateSeed(
      $table,
      $prefix,
      $suffix,
      $this->option('database'),
      $max,
      $chunkSize,
      $exclude,
      $prerunEvent,
      $postrunEvent,
      $dumpAuto,
      $indexed
     ),
     $table
    );
   }
  }

  return;
 }

 /**
  * Get the console command arguments.
  *
  * @return array
  */
 protected function getArguments() {
  return [
   [
    'tables',
    InputArgument::REQUIRED,
    'comma separated string of table names'
   ],
  ];
 }

 /**
  * Get the console command options.
  *
  * @return array
  */
 protected function getOptions() {
  return [
   [
    'clean',
    NULL,
    InputOption::VALUE_NONE,
    'clean iseed section',
    NULL
   ],
   [
    'force',
    NULL,
    InputOption::VALUE_NONE,
    'force overwrite of all existing seed classes',
    NULL
   ],
   [
    'database',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'database connection',
    \Config::get('database.default')
   ],
   [
    'max',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'max number of rows',
    NULL
   ],
   [
    'chunksize',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'size of data chunks for each insert query',
    NULL
   ],
   [
    'exclude',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'exclude columns',
    NULL
   ],
   [
    'prerun',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'prerun event name',
    NULL
   ],
   [
    'postrun',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'postrun event name',
    NULL
   ],
   [
    'dumpauto',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'run composer dump-autoload',
    TRUE
   ],
   [
    'noindex',
    NULL,
    InputOption::VALUE_NONE,
    'no indexing in the seed',
    NULL
   ],
   [
    'orderby',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'orderby desc by column',
    NULL
   ],
   [
    'direction',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'orderby direction',
    NULL
   ],
   [
    'classnameprefix',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'prefix for class and file name',
    NULL
   ],
   [
    'classnamesuffix',
    NULL,
    InputOption::VALUE_OPTIONAL,
    'suffix for class and file name',
    NULL
   ],
  ];
 }

 /**
  * Provide user feedback, based on success or not.
  *
  * @param  boolean  $successful
  * @param  string  $table
  *
  * @return void
  */
 protected function printResult($successful, $table) {
  if ($successful) {
   $this->info("Created a seed file from table {$table}");

   return;
  }

  $this->error("Could not create seed file from table {$table}");
 }

 /**
  * Generate file name, to be used in test wether seed file already exist
  *
  * @param  string  $table
  *
  * @return string
  */
 protected function generateFileName($table, $prefix = NULL, $suffix = NULL) {
  if (!\Schema::connection($this->option('database') ? $this->option('database') : config('database.default'))
              ->hasTable($table)) {
   throw new TableNotFoundException("Table $table was not found.");
  }

  // Generate class name and file name
  $className = app('iseed')->generateClassName($table, $prefix, $suffix);
  $seedPath  = base_path() . config('iseed::config.path');

  return [
   $seedPath . '/' . $className . '.php',
   $className . '.php'
  ];
 }
}
