# pittacusw/database

`pittacusw/database` is a Laravel package for:

- generating seeders from existing table data
- exporting each MySQL table to a gzipped SQL dump
- restoring SQL dump files back into a MySQL database

The package is intended for Laravel applications and targets Laravel `9.x` through `13.x`.

## Requirements

- PHP `^8.0.2`
- Laravel `^9.26|^10.0|^11.0|^12.0|^13.0`
- MySQL client binaries available on the host machine for `db:backup` and `db:restore`
  - `mysqldump`
  - `mysql`

## Installation

```bash
composer require pittacusw/database
```

Laravel package discovery will register the service provider and the `Iseed` facade automatically.

## Commands

### `php artisan iseed`

Generate seeders from one or more tables.

```bash
php artisan iseed users
php artisan iseed users,posts --force
php artisan iseed users --exclude=password,remember_token --orderby=id --direction=DESC
php artisan iseed audit_logs --max=1000 --chunksize=250 --dumpauto=0
```

Important options:

- `--force`: overwrite existing seed files without confirmation
- `--clean`: clear the managed `#iseed` section inside `database/seeders/DatabaseSeeder.php`
- `--database=`: choose a Laravel database connection
- `--max=`: limit exported rows
- `--chunksize=`: split inserts into smaller batches
- `--exclude=`: omit comma-separated columns
- `--noindex`: remove numeric indexes from exported arrays
- `--classnameprefix=` and `--classnamesuffix=`: customize the generated seeder class name

Generated files are written to `database/seeders`.

### `php artisan db:iseed`

Generate seeders for all tables except a small built-in ignore list:

- `migrations`
- `password_resets`
- `failed_jobs`
- `github_webhook_calls`
- `jobs`

Examples:

```bash
php artisan db:iseed
php artisan db:iseed users
php artisan db:iseed --database=legacy --chunksize=100
```

### `php artisan db:backup`

Create a gzipped SQL dump per table in `database/sql` by default.

```bash
php artisan db:backup
php artisan db:backup --database=legacy --path=storage/backups/sql
php artisan db:backup --mysqldump-binary="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe"
```

Notes:

- this command is MySQL-only
- dumps are executed through `mysqldump` using Symfony Process, not through a shell string
- output files are created as `*.sql.gz`
- one file is generated per table
- dump output is streamed directly into gzip files, so large tables are not buffered fully in PHP memory
- the database password is passed through `MYSQL_PWD` instead of a `--password=` CLI argument
- the command uses `--single-transaction`, `--quick`, `--skip-lock-tables`, and `--hex-blob`
- `--gzip-level=` accepts values from `0` to `9` and defaults to `6`

### `php artisan db:restore`

Restore `.sql` and `.sql.gz` files from `database/sql` by default.

```bash
php artisan db:restore
php artisan db:restore --database=legacy --path=storage/backups/sql
php artisan db:restore --mysql-binary="C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe"
```

Notes:

- this command is MySQL-only
- files are restored in filename order
- SQL input is wrapped with `SET FOREIGN_KEY_CHECKS=0/1` for safer restores
- `.sql` and `.sql.gz` files are streamed into `mysql`, so restore does not fully decompress a backup into memory first
- `--chunk-size=` controls the per-read streaming size and defaults to `1048576` bytes

## DatabaseSeeder markers

If you want `iseed` to manage a dedicated section inside `DatabaseSeeder`, add markers like this:

```php
public function run()
{
    #iseed_start
    #iseed_end
}
```

When the markers are present, generated seeders are inserted between them. Otherwise the package appends the seeder call inside `run()`.

## Compatibility notes

- The package assumes Laravel's standard `database/seeders` path.
- Seeder generation works through Laravel's database connection layer.
- Backup and restore commands require a MySQL connection configured in `config/database.php`.
- On Windows and Linux, the MySQL client binaries only need to be available on `PATH`, or you can pass explicit paths with `--mysqldump-binary` and `--mysql-binary`.

## Testing

```bash
vendor/bin/phpunit
```
