<?php

namespace Pittacusw\Database\Tests;

use Illuminate\Filesystem\Filesystem;
use Mockery as m;
use Pittacusw\Database\BackupDatabaseCommand;
use Pittacusw\Database\Iseed;
use Pittacusw\Database\IseedCommand;
use Pittacusw\Database\IseedDatabaseCommand;
use Pittacusw\Database\RestoreDatabaseCommand;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends TestCase
{
    public function testIseedCommandNormalizesOptionsBeforeGeneratingSeed()
    {
        $iseed = m::mock(Iseed::class);
        $iseed->shouldReceive('cleanSection')->never();
        $iseed->shouldReceive('generateSeed')->once()->with(
            'users',
            'Prefix',
            'Suffix',
            'testing',
            25,
            100,
            ['password', 'remember_token'],
            'BeforeUsersSeed',
            'AfterUsersSeed',
            false,
            false,
            'id',
            'DESC'
        )->andReturn(true);

        $this->useIseed($iseed);

        $command = new class(new Filesystem()) extends IseedCommand {
            public $fileNameResponse;

            protected function generateFileName($table, $prefix = null, $suffix = null)
            {
                return $this->fileNameResponse;
            }
        };
        $command->setLaravel($this->app);
        $command->fileNameResponse = [
            $this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders'.DIRECTORY_SEPARATOR.'PrefixUsersTableSuffixSeeder.php',
            'PrefixUsersTableSuffixSeeder.php',
        ];

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'tables' => 'users',
            '--database' => 'testing',
            '--max' => '25',
            '--chunksize' => '100',
            '--exclude' => 'password, remember_token',
            '--prerun' => 'BeforeUsersSeed',
            '--postrun' => 'AfterUsersSeed',
            '--dumpauto' => '0',
            '--noindex' => true,
            '--orderby' => 'id',
            '--direction' => 'desc',
            '--classnameprefix' => 'Prefix',
            '--classnamesuffix' => 'Suffix',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Created a seed file from table users', $tester->getDisplay());
    }

    public function testIseedDatabaseCommandSeedsEveryNonIgnoredTable()
    {
        $command = new class extends IseedDatabaseCommand {
            public $called;
            public $tableNamesResponse = [];

            public function call($command, array $arguments = [])
            {
                $this->called = [$command, $arguments];

                return 0;
            }

            protected function tableNames($connection)
            {
                return $this->tableNamesResponse;
            }
        };
        $command->setLaravel($this->app);
        $command->tableNamesResponse = [
            'migrations',
            'users',
            'jobs',
            'posts',
        ];

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--database' => 'testing',
            '--chunksize' => 250,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('iseed', $command->called[0]);
        $this->assertSame('users,posts', $command->called[1]['tables']);
        $this->assertTrue($command->called[1]['--force']);
        $this->assertTrue($command->called[1]['--clean']);
        $this->assertSame('testing', $command->called[1]['--database']);
        $this->assertFalse($command->called[1]['--dumpauto']);
        $this->assertSame(250, $command->called[1]['--chunksize']);
    }

    public function testIseedDatabaseCommandPopulatesTypedDatabaseSeeder()
    {
        $this->putDatabaseSeeder(<<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
    }
}
PHP
);

        $composer = m::mock(\Illuminate\Support\Composer::class, [$this->files, $this->basePath]);
        $composer->shouldReceive('dumpAutoloads')->never();

        $iseed = m::mock(Iseed::class, [$this->files, $composer])->makePartial();
        $iseed->shouldReceive('hasTable')->once()->with('users')->andReturn(true);
        $iseed->shouldReceive('getDataChunks')->once()->with('users', null, [], null, 'ASC', 100)->andReturn([
            [
                ['id' => 1, 'name' => 'One'],
            ],
        ]);

        $this->useIseed($iseed);

        $command = new class($this->files) extends IseedDatabaseCommand {
            public $tableNamesResponse = [];
            protected $files;

            public function __construct($files)
            {
                parent::__construct();
                $this->files = $files;
            }

            public function call($command, array $arguments = [])
            {
                if ($command !== 'iseed') {
                    return parent::call($command, $arguments);
                }

                $iseedCommand = new class($this->files) extends IseedCommand {
                    protected function generateFileName($table, $prefix = null, $suffix = null)
                    {
                        $className = app('iseed')->generateClassName($table, $prefix, $suffix);
                        $seedPath = app('iseed')->getSeedPath();

                        return [
                            app('iseed')->getPath($className, $seedPath),
                            $className.'.php',
                        ];
                    }
                };
                $iseedCommand->setLaravel($this->laravel);

                $tester = new CommandTester($iseedCommand);

                return $tester->execute($arguments);
            }

            protected function tableNames($connection)
            {
                return $this->tableNamesResponse;
            }
        };
        $command->setLaravel($this->app);
        $command->tableNamesResponse = ['users'];

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--database' => 'testing',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('$this->call(UsersTableSeeder::class);', $this->files->get($this->databaseSeederPath()));
        $this->assertFileExists($this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders'.DIRECTORY_SEPARATOR.'UsersTableSeeder.php');
    }

    public function testBackupCommandCreatesCompressedSqlFiles()
    {
        $command = new class($this->files) extends BackupDatabaseCommand {
            public $tableNamesResponse = [];
            public $processOutputs = [];
            public $lastCommand;
            public $lastEnvironment;

            protected function tableNames($connection)
            {
                return $this->tableNamesResponse;
            }

            protected function runProcess(array $command, array $environment = [], $input = null, callable $callback = null)
            {
                $this->lastCommand = $command;
                $this->lastEnvironment = $environment;

                foreach ((array) array_shift($this->processOutputs) as $chunk) {
                    $callback(\Symfony\Component\Process\Process::OUT, $chunk);
                }

                return '';
            }
        };
        $command->setLaravel($this->app);
        $command->tableNamesResponse = ['users', 'posts'];
        $command->processOutputs = [
            ['CREATE TABLE users ', "();\n"],
            ['CREATE TABLE posts ', "();\n"],
        ];

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--database' => 'testing',
            '--path' => 'database/sql',
            '--mysqldump-binary' => 'C:\\mysql\\bin\\mysqldump.exe',
            '--gzip-level' => 4,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame("CREATE TABLE users ();\n", gzdecode($this->files->get($this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'users.sql.gz')));
        $this->assertSame("CREATE TABLE posts ();\n", gzdecode($this->files->get($this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'posts.sql.gz')));
        $this->assertSame('C:\\mysql\\bin\\mysqldump.exe', $command->lastCommand[0]);
        $this->assertContains('--quick', $command->lastCommand);
        $this->assertContains('--skip-lock-tables', $command->lastCommand);
        $this->assertSame('secret', $command->lastEnvironment['MYSQL_PWD']);
        $this->assertFalse((bool) preg_grep('/^--password=/', $command->lastCommand));
    }

    public function testRestoreCommandDecompressesGzipFilesBeforeRunningMysql()
    {
        $directory = $this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'sql';
        $this->files->makeDirectory($directory, 0755, true);
        $this->files->put($directory.DIRECTORY_SEPARATOR.'users.sql.gz', gzencode('INSERT INTO users VALUES (1);'));

        $command = new class($this->files) extends RestoreDatabaseCommand {
            public $lastCommand;
            public $lastEnvironment;
            public $lastInput;

            protected function runProcess(array $command, array $environment = [], $input = null, callable $callback = null)
            {
                $this->lastCommand = $command;
                $this->lastEnvironment = $environment;
                $this->lastInput = '';

                foreach ($input as $chunk) {
                    $this->lastInput .= $chunk;
                }

                return '';
            }
        };
        $command->setLaravel($this->app);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--database' => 'testing',
            '--path' => 'database/sql',
            '--mysql-binary' => 'C:\\mysql\\bin\\mysql.exe',
            '--chunk-size' => 8,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('C:\\mysql\\bin\\mysql.exe', $command->lastCommand[0]);
        $this->assertSame('secret', $command->lastEnvironment['MYSQL_PWD']);
        $this->assertStringStartsWith("SET FOREIGN_KEY_CHECKS=0;\n", $command->lastInput);
        $this->assertStringContainsString('INSERT INTO users VALUES (1);', $command->lastInput);
        $this->assertStringEndsWith("SET FOREIGN_KEY_CHECKS=1;\n", $command->lastInput);
    }

    public function testRestoreCommandReadsPlainSqlFiles()
    {
        $directory = $this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'sql';
        $this->files->makeDirectory($directory, 0755, true);
        $this->files->put($directory.DIRECTORY_SEPARATOR.'posts.sql', "INSERT INTO posts VALUES (1);\n");

        $command = new class($this->files) extends RestoreDatabaseCommand {
            public $lastInput = '';

            protected function runProcess(array $command, array $environment = [], $input = null, callable $callback = null)
            {
                foreach ($input as $chunk) {
                    $this->lastInput .= $chunk;
                }

                return '';
            }
        };
        $command->setLaravel($this->app);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--database' => 'testing',
            '--path' => 'database/sql',
            '--chunk-size' => 4,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString("INSERT INTO posts VALUES (1);\n", $command->lastInput);
    }

    public function testRestoreCommandReadsUppercaseGzipExtensions()
    {
        $directory = $this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'sql';
        $this->files->makeDirectory($directory, 0755, true);
        $this->files->put($directory.DIRECTORY_SEPARATOR.'users.SQL.GZ', gzencode('INSERT INTO users VALUES (2);'));

        $command = new class($this->files) extends RestoreDatabaseCommand {
            public $lastInput = '';

            protected function runProcess(array $command, array $environment = [], $input = null, callable $callback = null)
            {
                foreach ($input as $chunk) {
                    $this->lastInput .= $chunk;
                }

                return '';
            }
        };
        $command->setLaravel($this->app);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--database' => 'testing',
            '--path' => 'database/sql',
            '--chunk-size' => 8,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('INSERT INTO users VALUES (2);', $command->lastInput);
    }
}
