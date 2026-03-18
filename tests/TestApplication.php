<?php

namespace Pittacusw\Database\Tests;

use Illuminate\Container\Container;

class TestApplication extends Container
{
    /**
     * @var string
     */
    private $basePath;

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    public function basePath($path = '')
    {
        return $path === ''
            ? $this->basePath
            : $this->basePath.DIRECTORY_SEPARATOR.$path;
    }

    public function path($path = '')
    {
        $appPath = $this->basePath('app');

        return $path === ''
            ? $appPath
            : $appPath.DIRECTORY_SEPARATOR.$path;
    }

    public function runningUnitTests()
    {
        return true;
    }
}
