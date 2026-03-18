<?php

namespace Pittacusw\Database\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Pittacusw\Database\Iseed;

abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var \Pittacusw\Database\Tests\TestApplication
     */
    protected $app;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pittacusw-database-'.uniqid('', true);
        $this->files = new Filesystem();
        $this->files->makeDirectory($this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders', 0755, true);

        $this->app = new TestApplication($this->basePath);
        $this->app->instance('config', new Repository([
            'database.default' => 'testing',
            'database.connections.testing' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'package_test',
                'username' => 'root',
                'password' => 'secret',
            ],
        ]));
        $this->app->instance('files', $this->files);

        Container::setInstance($this->app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);

        $this->useIseed();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        if ($this->files->isDirectory($this->basePath)) {
            $this->files->deleteDirectory($this->basePath);
        }

        parent::tearDown();
    }

    protected function databaseSeederPath()
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders'.DIRECTORY_SEPARATOR.'DatabaseSeeder.php';
    }

    protected function putDatabaseSeeder($contents)
    {
        $this->files->put($this->databaseSeederPath(), $contents);
    }

    protected function useIseed(Iseed $iseed = null)
    {
        $iseed = $iseed ?: new Iseed($this->files);

        $this->app->instance(Iseed::class, $iseed);
        $this->app->instance('iseed', $iseed);

        return $iseed;
    }
}
