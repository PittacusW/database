<?php

namespace Pittacusw\Database;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    use InteractsWithMysql;

    private const COMPRESS_CHUNK_SIZE = 1048576;

    /**
     * @var string
     */
    protected $signature = 'db:backup
        {--database= : Database connection}
        {--path=database/sql : Relative or absolute output directory}
        {--mysqldump-binary= : Path to the mysqldump binary}
        {--gzip-level=6 : Gzip compression level from 0 to 9}';

    /**
     * @var string
     */
    protected $description = 'Create gzipped SQL backups for each table in a MySQL database';

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
        $connection = $this->option('database') ?: config('database.default');
        $config = $this->resolveConnectionConfig($connection);
        $directory = $this->resolvePath($this->option('path'));
        $gzipLevel = $this->normalizeGzipLevel($this->option('gzip-level'));

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        foreach ($this->tableNames($connection) as $table) {
            $this->line('Backing up '.$table);

            $this->dumpTableToFile($config, $table, $directory.DIRECTORY_SEPARATOR.$table.'.sql.gz', $gzipLevel);

            $this->line('Backup of '.$table.' completed');
        }

        return self::SUCCESS;
    }

    protected function dumpTableToFile(array $config, $table, $path, $gzipLevel)
    {
        $dumpPath = $path.'.sql.part';
        $temporaryPath = $path.'.part';

        try {
            $this->runProcess($this->dumpCommand($config, $table, $dumpPath), $this->processEnvironment($config));
            $this->compressFile($dumpPath, $temporaryPath, $gzipLevel);
        } catch (\Throwable $exception) {
            $this->files->delete($temporaryPath);

            throw $exception;
        } finally {
            $this->files->delete($dumpPath);
        }

        if ($this->files->exists($path)) {
            $this->files->delete($path);
        }

        $this->files->move($temporaryPath, $path);
    }

    protected function compressFile($source, $destination, $gzipLevel)
    {
        $input = fopen($source, 'rb');

        if ($input === false) {
            throw new RuntimeException("Unable to open SQL dump [{$source}] for reading.");
        }

        $output = gzopen($destination, 'wb'.$gzipLevel);

        if ($output === false) {
            fclose($input);

            throw new RuntimeException("Unable to open backup file [{$destination}] for writing.");
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, self::COMPRESS_CHUNK_SIZE);

                if ($chunk === false) {
                    throw new RuntimeException("Unable to read SQL dump [{$source}].");
                }

                if ($chunk !== '' && gzwrite($output, $chunk) === false) {
                    throw new RuntimeException('Unable to write the SQL dump to the gzip stream.');
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }
    }

    protected function tableNames($connection)
    {
        return array_values(array_filter(array_map(static function ($row) {
            $values = array_values((array) $row);

            return $values[0] ?? null;
        }, DB::connection($connection)->select('SHOW TABLES'))));
    }

    protected function dumpCommand(array $config, $table, $resultFile)
    {
        return array_merge(
            [
                $this->binaryOption('mysqldump-binary', 'PITTACUSW_MYSQLDUMP_BINARY', 'mysqldump'),
                '--single-transaction',
                '--quick',
                '--skip-comments',
                '--skip-lock-tables',
                '--hex-blob',
                '--result-file='.$resultFile,
            ],
            $this->connectionArguments($config),
            [$config['database'], $table]
        );
    }

    protected function normalizeGzipLevel($gzipLevel)
    {
        $gzipLevel = (int) $gzipLevel;

        if ($gzipLevel < 0 || $gzipLevel > 9) {
            throw new RuntimeException('The gzip level must be between 0 and 9.');
        }

        return $gzipLevel;
    }

    protected function runProcess(array $command, array $environment = [], $input = null, callable $callback = null)
    {
        $process = new Process($command, null, $environment);
        $process->setTimeout(null);

        if ($input !== null) {
            $process->setInput($input);
        }

        $process->run($callback);

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
