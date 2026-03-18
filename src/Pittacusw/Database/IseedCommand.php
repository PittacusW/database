<?php

namespace Pittacusw\Database;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

class IseedCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'iseed
        {tables : Comma separated list of table names}
        {--clean : Clean the #iseed section in DatabaseSeeder.php}
        {--force : Overwrite existing seed classes without confirmation}
        {--database= : Database connection}
        {--max= : Max number of rows to export}
        {--chunksize= : Rows per insert statement}
        {--exclude= : Comma separated columns to exclude}
        {--prerun= : Comma separated prerun event class names}
        {--postrun= : Comma separated postrun event class names}
        {--dumpauto=1 : Run composer dump-autoload after generating files}
        {--noindex : Omit numeric indexes from generated arrays}
        {--orderby= : Column used to order exported rows}
        {--direction=ASC : Order direction (ASC or DESC)}
        {--classnameprefix= : Prefix for the generated class name}
        {--classnamesuffix= : Suffix for the generated class name}';

    /**
     * @var string
     */
    protected $description = 'Generate seed files from database tables';

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    public function __construct(Filesystem $files = null)
    {
        parent::__construct();

        $this->files = $files ?: new Filesystem();
    }

    public function handle()
    {
        $iseed = app('iseed');

        if ($this->option('clean')) {
            $iseed->cleanSection();
        }

        $tables = $this->csvValues($this->argument('tables'));
        $exclude = $this->csvValues($this->option('exclude'));
        $prerunEvents = $this->csvValues($this->option('prerun'));
        $postrunEvents = $this->csvValues($this->option('postrun'));
        $max = $this->normalizeNullableInt($this->option('max'));
        $chunkSize = $this->normalizeNullableInt($this->option('chunksize'));
        $dumpAuto = $this->booleanOption('dumpauto', true);
        $indexed = ! $this->option('noindex');
        $orderBy = $this->option('orderby') ?: null;
        $direction = $this->normalizeDirection($this->option('direction'));
        $prefix = $this->option('classnameprefix') ?: null;
        $suffix = $this->option('classnamesuffix') ?: null;

        foreach ($tables as $index => $table) {
            $prerunEvent = $prerunEvents[$index] ?? null;
            $postrunEvent = $postrunEvents[$index] ?? null;

            [$fileName, $className] = $this->generateFileName($table, $prefix, $suffix);

            if ($this->files->exists($fileName) && ! $this->option('force') && ! $this->confirm('File '.$className.' already exists. Do you wish to override it?')) {
                continue;
            }

            $this->printResult(
                $iseed->generateSeed(
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
        }

        return self::SUCCESS;
    }

    protected function printResult($successful, $table)
    {
        if ($successful) {
            $this->info("Created a seed file from table {$table}");

            return;
        }

        $this->error("Could not create seed file from table {$table}");
    }

    protected function generateFileName($table, $prefix = null, $suffix = null)
    {
        $database = $this->option('database') ?: config('database.default');

        if (! Schema::connection($database)->hasTable($table)) {
            throw new TableNotFoundException("Table {$table} was not found.");
        }

        $className = app('iseed')->generateClassName($table, $prefix, $suffix);
        $seedPath = app('iseed')->getSeedPath();

        return [
            app('iseed')->getPath($className, $seedPath),
            $className.'.php',
        ];
    }

    protected function csvValues($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static function ($item) {
            return $item !== '';
        }));
    }

    protected function normalizeNullableInt($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    protected function normalizeDirection($direction)
    {
        $direction = strtoupper((string) $direction);

        return in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'ASC';
    }

    protected function booleanOption($name, $default)
    {
        $value = $this->option($name);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed === null ? $default : $parsed;
    }
}
