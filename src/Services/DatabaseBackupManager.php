<?php

declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers\Services;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseBackupManager
{
    public function backup($disk= 'local'): string
    {
        $connectionName = config('database.default');
        $config = config("database.connections.{$connectionName}");
        Storage::disk($disk);

        $timestamp = now()->format('Ymd_His');

        $extension = match ($config['driver']) {
            'sqlite' => 'sqlite',
            'mysql', 'mariadb' => 'sql',
            default => 'sql',
        };

        $filename = Str::slug(config('app.name')).'_'.Str::slug($connectionName).'_'.$timestamp.'.'.$extension;

        if(config('hdd-laravel-helpers.database-backup.gzip_compress')){
            try {
                $gzipProcess = \Symfony\Component\Process\Process::fromShellCommandline("gzip \"$filename\"");
                $gzipProcess->setTimeout(300);
                $gzipProcess->run();
                if ($gzipProcess->isSuccessful()) {
                    $filename = $filename.'.gz';
                }
            }catch (Exception ){

            }
        }

        $backupPath = Storage::disk($disk)->path($filename);

        return match ($config['driver']) {
            'sqlite' => $this->backupSqliteDatabase($config, $backupPath),
            'mysql', 'mariadb' => $this->backupMysqlDatabase($config, $backupPath),
            'pgsql' => $this->backupPostgresDatabase($config, $backupPath),
            default => throw new RuntimeException('Unsupported database driver for backups.'),
        };
    }

    public function restore(string $backupPath): void
    {
        $connectionName = config('database.default');
        $config = config("database.connections.{$connectionName}");

        match ($config['driver']) {
            'sqlite' => $this->restoreSqliteDatabase($config, $backupPath),
            'mysql', 'mariadb' => $this->restoreMysqlDatabase($config, $backupPath),
            'pgsql' => $this->restorePostgresDatabase($config, $backupPath),
            default => throw new RuntimeException('Unsupported database driver for restores.'),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function backupSqliteDatabase(array $config, string $filepath): string
    {
        if (! isset($config['database'])) {
            throw new RuntimeException('SQLite database path is not configured.');
        }

        if (! copy($config['database'], $filepath)) {
            throw new RuntimeException('Unable to create SQLite database backup.');
        }

        return $filepath;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function backupMysqlDatabase(array $config, string $filepath): string
    {
        $command = sprintf(
            'mysqldump --single-transaction --quick --skip-lock-tables -h %s -P %s -u %s %s %s > %s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            $config['username'],
            isset($config['password']) && $config['password'] !== '' ? '-p'.$config['password'] : '',
            $config['database'],
            escapeshellarg($filepath) // very important
        );

        $process = \Symfony\Component\Process\Process::fromShellCommandline($command);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }

        return $filepath;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function backupPostgresDatabase(array $config, string $filpath): string
    {
        $command = $this->buildPostgresCommand($config, binary: 'pg_dump');
        $result = Process::env($this->buildPostgresEnvironment($config))->run($command);
        if (! $result->successful()) {
            throw new RuntimeException($result->errorOutput());
        }
        file_put_contents($filpath, $result->output());
        return $filpath;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function restoreSqliteDatabase(array $config, string $backupPath): void
    {
        if (! isset($config['database'])) {
            throw new RuntimeException('SQLite database path is not configured.');
        }

        if (! copy($backupPath, $config['database'])) {
            throw new RuntimeException('Unable to restore SQLite database.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function restoreMysqlDatabase(array $config, string $backupPath): void
    {

        $command = $this->buildMysqlCommand($config, binary: config('services.bin.mysql_client_binary'));

        $contents = file_get_contents($backupPath);

        if ($contents === false) {
            $contents = '';
        }

        if (str_ends_with($backupPath, '.gz')) {
            if (! function_exists('gzdecode')) {
                throw new RuntimeException('zlib extension is required to restore compressed MySQL backups (.gz).');
            }

            $decoded = gzdecode($contents);
            if ($decoded === false) {
                throw new RuntimeException('Unable to decompress MySQL backup (.gz).');
            }

            $contents = $decoded;
        }

        $result = Process::env($this->buildMysqlEnvironment($config))
            ->input($contents)
            ->run($command);

        if (! $result->successful()) {
            throw new RuntimeException($result->errorOutput());
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function restorePostgresDatabase(array $config, string $backupPath): void
    {
        $command = $this->buildPostgresCommand($config, binary: 'psql');
        $sql = file_get_contents($backupPath);

        $result = Process::env($this->buildPostgresEnvironment($config))
            ->input($sql === false ? '' : $sql)
            ->run($command);

        if (! $result->successful()) {
            throw new RuntimeException($result->errorOutput());
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    protected function buildPostgresEnvironment(array $config): array
    {
        $password = Arr::get($config, 'password');

        if ($password === null || $password === '') {
            return [];
        }

        return [
            'PGPASSWORD' => $password,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    protected function buildMysqlEnvironment(array $config): array
    {
        $password = Arr::get($config, 'password');

        if ($password === null || $password === '') {
            return [];
        }

        // Avoid passing password on the command line.
        return [
            'MYSQL_PWD' => (string) $password,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function buildMysqlCommand(array $config, string $binary): string
    {
        $parts = array_filter([
            $binary,
            '--host='.(string) Arr::get($config, 'host', '127.0.0.1'),
            '--port='.(string) Arr::get($config, 'port', 3306),
            '--user='.(string) Arr::get($config, 'username', ''),
            (string) Arr::get($config, 'database', ''),
        ], fn ($v) => $v !== '');

        return implode(' ', array_map('escapeshellarg', $parts));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function buildPostgresCommand(array $config, string $binary): string
    {
        $parts = array_filter([
            $binary,
            '--host='.(string) Arr::get($config, 'host', '127.0.0.1'),
            '--port='.(string) Arr::get($config, 'port', 5432),
            '--username='.(string) Arr::get($config, 'username', ''),
            (string) Arr::get($config, 'database', ''),
        ], fn ($v) => $v !== '');

        return implode(' ', array_map('escapeshellarg', $parts));
    }
}
