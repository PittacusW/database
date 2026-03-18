<?php

namespace Pittacusw\Database;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IseedDatabaseCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'db:iseed
        {table? : Table name to export. When omitted, every non-ignored table is exported}
        {--database= : Database connection}
        {--chunksize=100 : Rows per insert statement}';

    /**
     * @var string
     */
    protected $description = 'Create seeders for every table or for a specified table';

    /**
     * @var array<int, string>
     */
    protected $ignore = [
        'migrations',
        'password_resets',
        'failed_jobs',
        'github_webhook_calls',
        'jobs',
    ];

    public function handle()
    {
        $table = $this->argument('table');
        $database = $this->option('database') ?: config('database.default');
        $tables = $table ? [$table] : array_values(array_diff($this->tableNames($database), $this->ignore));

        if ($tables === []) {
            $this->warn('No tables found to seed.');

            return self::SUCCESS;
        }

        $this->call('iseed', [
            'tables' => implode(',', $tables),
            '--force' => true,
            '--clean' => $table === null,
            '--database' => $database,
            '--dumpauto' => false,
            '--chunksize' => (int) $this->option('chunksize'),
        ]);

        return self::SUCCESS;
    }

    protected function tableNames($connection)
    {
        return array_values(array_filter(array_map(static function ($row) {
            $values = array_values((array) $row);

            return $values[0] ?? null;
        }, DB::connection($connection)->select('SHOW TABLES'))));
    }
}
