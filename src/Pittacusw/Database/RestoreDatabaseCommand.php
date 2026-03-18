<?php

namespace Pittacusw\Database;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class RestoreDatabaseCommand extends Command
{
    use InteractsWithMysql;

    /**
     * @var string
     */
    protected $signature = 'db:restore
        {--database= : Database connection}
        {--path=database/sql : Relative or absolute directory containing .sql or .sql.gz files}
        {--mysql-binary= : Path to the mysql binary}
        {--chunk-size=1048576 : Number of bytes streamed per read during restore}';

    /**
     * @var string
     */
    protected $description = 'Restore a MySQL database from SQL backup files';

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
        $chunkSize = $this->normalizeChunkSize($this->option('chunk-size'));

        if (! $this->files->isDirectory($directory)) {
            throw new RuntimeException("Backup directory [{$directory}] does not exist.");
        }

        foreach ($this->backupFiles($directory) as $file) {
            $name = $file->getFilename();

            $this->line('Restoring '.$name);
            $this->restoreFile($config, $file->getPathname(), $chunkSize);
            $this->line('Restore of '.$name.' completed');
        }

        return self::SUCCESS;
    }

    protected function restoreFile(array $config, $path, $chunkSize)
    {
        $this->runProcess(
            $this->restoreCommand($config),
            $this->processEnvironment($config),
            $this->sqlInputChunks($path, $chunkSize)
        );
    }

    protected function backupFiles($directory)
    {
        $files = array_values(array_filter($this->files->files($directory), static function ($file) {
            return preg_match('/\.sql(?:\.gz)?$/i', $file->getFilename()) === 1;
        }));

        usort($files, static function ($left, $right) {
            return strcmp($left->getFilename(), $right->getFilename());
        });

        return $files;
    }

    protected function restoreCommand(array $config)
    {
        return array_merge(
            [$this->binaryOption('mysql-binary', 'PITTACUSW_MYSQL_BINARY', 'mysql')],
            $this->connectionArguments($config),
            [$config['database']]
        );
    }

    protected function normalizeChunkSize($chunkSize)
    {
        $chunkSize = (int) $chunkSize;

        if ($chunkSize < 1) {
            throw new RuntimeException('The chunk size must be greater than zero.');
        }

        return $chunkSize;
    }

    protected function sqlInputChunks($path, $chunkSize)
    {
        yield "SET FOREIGN_KEY_CHECKS=0;\n";

        foreach ($this->readSqlChunks($path, $chunkSize) as $chunk) {
            yield $chunk;
        }

        yield "\nSET FOREIGN_KEY_CHECKS=1;\n";
    }

    protected function readSqlChunks($path, $chunkSize)
    {
        if (preg_match('/\.sql\.gz$/i', $path) === 1) {
            $handle = gzopen($path, 'rb');

            if ($handle === false) {
                throw new RuntimeException("Backup file [{$path}] is not a valid gzip archive.");
            }

            try {
                while (! gzeof($handle)) {
                    $chunk = gzread($handle, $chunkSize);

                    if ($chunk === false) {
                        throw new RuntimeException("Unable to read backup file [{$path}].");
                    }

                    if ($chunk !== '') {
                        yield $chunk;
                    }
                }
            } finally {
                gzclose($handle);
            }

            return;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open backup file [{$path}] for reading.");
        }

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, $chunkSize);

                if ($chunk === false) {
                    throw new RuntimeException("Unable to read backup file [{$path}].");
                }

                if ($chunk !== '') {
                    yield $chunk;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    protected function runProcess(array $command, array $environment = [], $input = null, callable $callback = null)
    {
        $process = new Process($command, null, $environment);
        $process->setTimeout(null);

        if (is_iterable($input)) {
            $inputStream = new InputStream();
            $process->setInput($inputStream);
            $process->start($callback);

            try {
                foreach ($input as $chunk) {
                    $inputStream->write($chunk);
                }
            } finally {
                $inputStream->close();
            }

            $process->wait($callback);
        } else {
            if ($input !== null) {
                $process->setInput($input);
            }

            $process->run($callback);
        }

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
