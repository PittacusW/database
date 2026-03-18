<?php

namespace Pittacusw\Database;

use RuntimeException;

trait InteractsWithMysql
{
    protected function resolveConnectionConfig($connection)
    {
        $config = config("database.connections.{$connection}");

        if (! is_array($config)) {
            throw new RuntimeException("Database connection [{$connection}] is not configured.");
        }

        if (($config['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('This command only supports MySQL connections.');
        }

        if (empty($config['database']) || empty($config['username'])) {
            throw new RuntimeException("Database connection [{$connection}] must define database and username values.");
        }

        return $config;
    }

    protected function connectionArguments(array $config)
    {
        $arguments = [];

        if (! empty($config['host'])) {
            $arguments[] = '--host='.$config['host'];
        }

        if (! empty($config['port'])) {
            $arguments[] = '--port='.(string) $config['port'];
        }

        if (! empty($config['unix_socket'])) {
            $arguments[] = '--socket='.$config['unix_socket'];
        }

        if (! empty($config['charset'])) {
            $arguments[] = '--default-character-set='.$config['charset'];
        }

        $arguments[] = '--user='.(string) $config['username'];

        return $arguments;
    }

    protected function processEnvironment(array $config)
    {
        $environment = [];

        if (array_key_exists('password', $config) && $config['password'] !== null && $config['password'] !== '') {
            $environment['MYSQL_PWD'] = (string) $config['password'];
        }

        return $environment;
    }

    protected function binaryOption($option, $environmentVariable, $default)
    {
        $value = $this->option($option);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $value = getenv($environmentVariable);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    protected function resolvePath($path)
    {
        $path = $path ?: 'database/sql';

        if (preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\\\\\\\\|\\/)/', $path) === 1) {
            return $path;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }
}
