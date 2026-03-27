<?php

namespace Pittacusw\Database;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Iseed
{
    /**
     * @var string|null
     */
    protected $databaseName;

    /**
     * @var string
     */
    private $newLineCharacter = PHP_EOL;

    /**
     * @var string
     */
    private $indentCharacter = '    ';

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    private $files;

    /**
     * @var \Illuminate\Support\Composer
     */
    private $composer;

    public function __construct(Filesystem $filesystem = null, Composer $composer = null)
    {
        $this->files = $filesystem ?: new Filesystem();

        $this->composer = $composer ?: new Composer(
            $this->files,
            function_exists('base_path') ? base_path() : getcwd()
        );
    }

    public function readStubFile($file)
    {
        return $this->files->get($file);
    }

    /**
     * @throws \Pittacusw\Database\TableNotFoundException
     */
    public function generateSeed(
        $table,
        $prefix = null,
        $suffix = null,
        $database = null,
        $max = 0,
        $chunkSize = 0,
        $exclude = null,
        $prerunEvent = null,
        $postrunEvent = null,
        $dumpAuto = true,
        $indexed = true,
        $orderBy = null,
        $direction = 'ASC'
    ) {
        if (! $database) {
            $database = config('database.default');
        }

        $this->databaseName = $database;

        if (! $this->hasTable($table)) {
            throw new TableNotFoundException("Table {$table} was not found.");
        }

        $className = $this->generateClassName($table, $prefix, $suffix);
        $stub = $this->readStubFile($this->getStubPath().DIRECTORY_SEPARATOR.'seed.stub');
        $seedPath = $this->getSeedPath();
        $writeChunkSize = $chunkSize ?: 500;

        if (! $this->files->isDirectory($seedPath)) {
            $this->files->makeDirectory($seedPath, 0755, true);
        }

        $seedsPath = $this->getPath($className, $seedPath);
        [$seedHeader, $seedFooter] = $this->splitStubInsertStatements($this->populateStubMetadata(
            $className,
            $stub,
            $table,
            $prerunEvent,
            $postrunEvent
        ));

        $this->files->put($seedsPath, $seedHeader);

        foreach ($this->getDataChunks($table, $max, $exclude, $orderBy, $direction, $writeChunkSize) as $chunk) {
            $dataArray = $this->repackSeedData($chunk);

            if ($dataArray === []) {
                continue;
            }

            $this->files->append($seedsPath, $this->buildInsertStatements($table, $dataArray, $indexed));
        }

        $this->files->append($seedsPath, $seedFooter);

        if ($dumpAuto) {
            $this->composer->dumpAutoloads();
        }

        return $this->updateDatabaseSeederRunMethod($className) !== false;
    }

    public function getSeedPath()
    {
        return base_path('database/seeders');
    }

    public function getData($table, $max, $exclude = null, $orderBy = null, $direction = 'ASC')
    {
        DB::connection($this->databaseName)->statement("SET time_zone = '+00:00'");

        $query = $this->buildDataQuery($table, $exclude, $orderBy, $direction);

        if ($max) {
            $query = $query->limit($max);
        }

        return $query->get();
    }

    public function getDataChunks($table, $max, $exclude = null, $orderBy = null, $direction = 'ASC', $chunkSize = 500)
    {
        $chunkSize = $chunkSize ?: 500;

        DB::connection($this->databaseName)->statement("SET time_zone = '+00:00'");

        $query = $this->buildDataQuery($table, $exclude, $orderBy, $direction, true);
        $rows = $query->lazy($chunkSize);
        $processed = 0;
        $chunk = [];

        foreach ($rows as $row) {
            if ($max && $processed >= $max) {
                break;
            }

            $chunk[] = $row;
            $processed++;

            if (count($chunk) === $chunkSize) {
                yield $chunk;
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            yield $chunk;
        }
    }

    public function repackSeedData($data)
    {
        if (! is_array($data)) {
            $data = $data->toArray();
        }

        $dataArray = [];

        foreach ($data as $row) {
            $rowArray = [];

            foreach ($row as $columnName => $columnValue) {
                if ($columnValue === '0000-00-00 00:00:00' || $columnValue === '0000-00-00') {
                    $columnValue = null;
                }

                $rowArray[$columnName] = $columnValue;
            }

            $dataArray[] = $rowArray;
        }

        return $dataArray;
    }

    public function hasTable($table)
    {
        return Schema::connection($this->databaseName)->hasTable($table);
    }

    public function generateClassName($table, $prefix = null, $suffix = null)
    {
        $tableString = str_replace('_', '', ucwords($table, '_'));

        return ($prefix ?: '').ucfirst($tableString).'Table'.($suffix ?: '').'Seeder';
    }

    public function getStubPath()
    {
        return __DIR__.DIRECTORY_SEPARATOR.'stubs';
    }

    public function populateStub(
        $class,
        $stub,
        $table,
        $data,
        $chunkSize = null,
        $prerunEvent = null,
        $postrunEvent = null,
        $indexed = true
    ) {
        return str_replace(
            '{{insert_statements}}',
            $this->buildInsertStatements($table, $data, $indexed, $chunkSize),
            $this->populateStubMetadata($class, $stub, $table, $prerunEvent, $postrunEvent)
        );
    }

    public function getPath($name, $path)
    {
        return $path.DIRECTORY_SEPARATOR.$name.'.php';
    }

    public function cleanSection()
    {
        $databaseSeederPath = $this->databaseSeederPath();

        if (! $this->files->exists($databaseSeederPath)) {
            return true;
        }

        $content = $this->files->get($databaseSeederPath);
        $replacement = '#iseed_start'.$this->newLineCharacter
            .$this->indentCharacter.$this->indentCharacter
            .'#iseed_end';

        $content = preg_replace('/#iseed_start.*?#iseed_end/s', $replacement, $content);

        return $this->files->put($databaseSeederPath, $content) !== false;
    }

    public function updateDatabaseSeederRunMethod($className)
    {
        $databaseSeederPath = $this->databaseSeederPath();

        if (! $this->files->exists($databaseSeederPath)) {
            return true;
        }

        $content = $this->files->get($databaseSeederPath);
        $call = "\$this->call({$className}::class);";

        if (strpos($content, $call) !== false) {
            return true;
        }

        $markerStart = strpos($content, '#iseed_start');
        $markerEnd = strpos($content, '#iseed_end');

        if ($markerStart !== false && $markerEnd !== false && $markerStart < $markerEnd) {
            $content = preg_replace(
                '/(#iseed_start.*?)([ \t]*#iseed_end)/s',
                '$1'.$this->newLineCharacter.$this->indentCharacter.$this->indentCharacter.$call.$this->newLineCharacter.'$2',
                $content,
                1
            );
        } else {
            $content = preg_replace(
                '/(public function run\(\)\s*(?::\s*[^\{]+)?\s*\{)(.*?)(\n\s*\})/s',
                '$1$2'.$this->newLineCharacter.$this->indentCharacter.$this->indentCharacter.$call.'$3',
                $content,
                1
            );
        }

        return $this->files->put($databaseSeederPath, $content) !== false;
    }

    protected function prettifyArray($array, $indexed = true)
    {
        $content = $indexed
            ? var_export($array, true)
            : preg_replace('/[0-9]+ \=\>/i', '', var_export($array, true));

        $lines = explode("\n", $content);
        $inString = false;
        $tabCount = 3;

        for ($i = 1; $i < count($lines); $i++) {
            $lines[$i] = ltrim($lines[$i]);

            if (strpos($lines[$i], ')') !== false) {
                $tabCount--;
            }

            if ($inString === false) {
                for ($j = 0; $j < $tabCount; $j++) {
                    $lines[$i] = substr_replace($lines[$i], $this->indentCharacter, 0, 0);
                }
            }

            for ($j = 0; $j < strlen($lines[$i]); $j++) {
                if ($lines[$i][$j] === '\\') {
                    $j++;
                    continue;
                }

                if ($lines[$i][$j] === '\'') {
                    $inString = ! $inString;
                }
            }

            if (strpos($lines[$i], '(') !== false) {
                $tabCount++;
            }
        }

        return implode("\n", $lines);
    }

    protected function buildInsertStatements($table, $data, $indexed = true, $chunkSize = null)
    {
        $chunkSize = $chunkSize ?: 500;
        $inserts = '';

        foreach (array_chunk($data, $chunkSize) as $chunk) {
            $this->addNewLines($inserts);
            $this->addIndent($inserts, 2);
            $inserts .= sprintf(
                "DB::table('%s')->insert(%s);",
                $table,
                $this->prettifyArray($chunk, $indexed)
            );
        }

        return $inserts;
    }

    private function addNewLines(&$content, $numberOfLines = 1)
    {
        $content .= str_repeat($this->newLineCharacter, $numberOfLines);
    }

    private function addIndent(&$content, $numberOfIndents = 1)
    {
        $content .= str_repeat($this->indentCharacter, $numberOfIndents);
    }

    private function buildEventBlock($eventClass, $failureMessage)
    {
        if (! $eventClass) {
            return '';
        }

        $eventBlock = "\$response = Event::until(new {$eventClass}());";
        $this->addNewLines($eventBlock);
        $this->addIndent($eventBlock, 2);
        $eventBlock .= 'if ($response === false) {';
        $this->addNewLines($eventBlock);
        $this->addIndent($eventBlock, 3);
        $eventBlock .= 'throw new Exception("'.$failureMessage.'");';
        $this->addNewLines($eventBlock);
        $this->addIndent($eventBlock, 2);
        $eventBlock .= '}';

        return $eventBlock;
    }

    private function buildDataQuery($table, $exclude = null, $orderBy = null, $direction = 'ASC', $ensureOrder = false)
    {
        $query = DB::connection($this->databaseName)->table($table);
        $exclude = array_values(array_filter((array) $exclude, static function ($column) {
            return $column !== null && $column !== '';
        }));

        if ($exclude !== []) {
            $allColumns = DB::connection($this->databaseName)
                ->getSchemaBuilder()
                ->getColumnListing($table);

            $query = $query->select(array_values(array_diff($allColumns, $exclude)));
        }

        $sortColumn = $orderBy ?: ($ensureOrder ? $this->defaultOrderByColumn($table, $exclude) : null);

        if ($sortColumn) {
            $direction = strtoupper((string) $direction);
            $query = $query->orderBy($sortColumn, in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'ASC');
        }

        return $query;
    }

    private function defaultOrderByColumn($table, array $exclude = [])
    {
        $columns = DB::connection($this->databaseName)
            ->getSchemaBuilder()
            ->getColumnListing($table);
        $availableColumns = array_values(array_diff($columns, $exclude));

        return $availableColumns[0] ?? null;
    }

    private function populateStubMetadata($class, $stub, $table, $prerunEvent = null, $postrunEvent = null)
    {
        $stub = str_replace('{{class}}', $class, $stub);
        $stub = str_replace('{{prerun_event}}', $this->buildEventBlock($prerunEvent, "Prerun event failed, seed wasn't executed!"), $stub);

        if ($table !== null) {
            $stub = str_replace('{{table}}', $table, $stub);
        }

        return str_replace('{{postrun_event}}', $this->buildEventBlock($postrunEvent, 'Seed was executed but the postrun event failed!'), $stub);
    }

    private function splitStubInsertStatements($stub)
    {
        $parts = explode('{{insert_statements}}', $stub, 2);

        return count($parts) === 2 ? $parts : [$stub, ''];
    }

    private function databaseSeederPath()
    {
        return base_path('database/seeders/DatabaseSeeder.php');
    }
}
