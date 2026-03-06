<?php

declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers\Services;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseBackupManager
{
    public function backup($disk = 'local'): string
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

        $filename = Str::slug(config('app.name')) . '_' . Str::slug($connectionName) . '_' . $timestamp . '.' . $extension;

        $backupPath = Storage::disk($disk)->path($filename);

        $result = match ($config['driver']) {
            'sqlite' => $this->backupSqliteDatabase($config, $backupPath),
            'mysql', 'mariadb' => $this->backupMysqlDatabase($config, $backupPath),
            'pgsql' => $this->backupPostgresDatabase($config, $backupPath),
            default => throw new RuntimeException('Unsupported database driver for backups.'),
        };

        if (config()->boolean('hdd-laravel-helpers.database-backup.gzip_compress', true)) {
            try {
                $gzipBinaryPath = config('hdd-laravel-helpers.database-backup.gzip_binary');
                $gzipProcess = \Symfony\Component\Process\Process::fromShellCommandline("\"$gzipBinaryPath\" \"$result\"");
                $gzipProcess->setTimeout(300);
                $gzipProcess->run();
                if ($gzipProcess->isSuccessful()) {
                    $result = $result . '.gz';
                } else {
                    Log::error($gzipProcess->getErrorOutput());
                }
            } catch (Exception $exception) {
                Log::error($exception->getMessage(), $exception->getTrace());
            }
        }

        return $result;
    }

    public function restore(string $backupPath, $migrate = false): void
    {
        $connectionName = config('database.default');
        $config = config("database.connections.{$connectionName}");

        $uncompressedBackupPath = $backupPath;
        if ($this->isGzipCompressed($backupPath)) {
            $extractPath = Str::beforeLast($backupPath, '.');
            $gzipBinaryPath = config('hdd-laravel-helpers.database-backup.gzip_binary');
            $gzipProcess = \Symfony\Component\Process\Process::fromShellCommandline("\"$gzipBinaryPath\" -dc \"$backupPath\" > \"$extractPath\"");
            $gzipProcess->setTimeout(300);
            $gzipProcess->run();
            if ($gzipProcess->isSuccessful()) {
                $uncompressedBackupPath = $extractPath;
            } else {
                Log::error($gzipProcess->getErrorOutput());
            }
        }

        match ($config['driver']) {
            'sqlite' => $this->restoreSqliteDatabase($config, $uncompressedBackupPath),
            'mysql', 'mariadb' => $this->restoreMysqlDatabase($config, $uncompressedBackupPath),
            'pgsql' => $this->restorePostgresDatabase($config, $uncompressedBackupPath),
            default => throw new RuntimeException('Unsupported database driver for restores.'),
        };

        if ($uncompressedBackupPath !== $backupPath) {
            @unlink($uncompressedBackupPath);
        }

        if($migrate){
            Artisan::call('migrate', ['--force' => true]);
        }

    }

    /**
     * @param array<string, mixed> $config
     */
    protected function backupSqliteDatabase(array $config, string $filepath): string
    {
        if (!isset($config['database'])) {
            throw new RuntimeException('SQLite database path is not configured.');
        }

        if (!copy($config['database'], $filepath)) {
            throw new RuntimeException('Unable to create SQLite database backup.');
        }

        return $filepath;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function backupMysqlDatabase(array $config, string $filepath): string
    {
        $binary = config('hdd-laravel-helpers.database-backup.mysqldump_binary', 'mysqldump');
        $command =
            $binary
            . ' '
            . sprintf(
            '--single-transaction --quick --skip-lock-tables -h %s -P %s -u %s %s > %s',
            escapeshellarg($config['host'] ?? '127.0.0.1'),
            escapeshellarg($config['port'] ?? '3306'),
            escapeshellarg($config['username']),
            escapeshellarg($config['database']),
            escapeshellarg($filepath) // very important
        );

        $env = [];
        if (!empty($config['password'])) {
            $env['MYSQL_PWD'] = $config['password'];
        }

        $process = \Symfony\Component\Process\Process::fromShellCommandline($command, null, $env);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }

        return $filepath;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function backupPostgresDatabase(array $config, string $filpath): string
    {
        $command = $this->buildPostgresCommand($config, binary: 'pg_dump');
        $result = Process::env($this->buildPostgresEnvironment($config))->run($command);
        if (!$result->successful()) {
            throw new RuntimeException($result->errorOutput());
        }
        file_put_contents($filpath, $result->output());
        return $filpath;
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function restoreSqliteDatabase(array $config, string $backupPath): void
    {
        if (!isset($config['database'])) {
            throw new RuntimeException('SQLite database path is not configured.');
        }

        if (!copy($backupPath, $config['database'])) {
            throw new RuntimeException('Unable to restore SQLite database.');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function restoreMysqlDatabase(array $config, string $backupPath): void
    {
        $binary = config('hdd-laravel-helpers.database-backup.mysql_client_binary', 'mysql');
        $command =
            $binary
            . ' '
            . sprintf(
                '-h %s -P %s -u %s %s < %s',
                escapeshellarg($config['host'] ?? '127.0.0.1'),
                escapeshellarg($config['port'] ?? '3306'),
                escapeshellarg($config['username']),
                escapeshellarg($config['database']),
                escapeshellarg($backupPath) // very important
            );
        $env = [];
        if (!empty($config['password'])) {
            $env['MYSQL_PWD'] = $config['password'];
        }
        $process = \Symfony\Component\Process\Process::fromShellCommandline($command,null,$env);
        $process->setTimeout(0);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function restorePostgresDatabase(array $config, string $backupPath): void
    {
        $command = $this->buildPostgresCommand($config, binary: 'psql');
        $sql = file_get_contents($backupPath);

        $result = Process::env($this->buildPostgresEnvironment($config))
            ->input($sql === false ? '' : $sql)
            ->run($command);

        if (!$result->successful()) {
            throw new RuntimeException($result->errorOutput());
        }
    }

    /**
     * @param array<string, mixed> $config
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
     * @param array<string, mixed> $config
     */
    protected function buildPostgresCommand(array $config, string $binary): string
    {
        $parts = array_filter([
            $binary,
            '--host=' . (string)Arr::get($config, 'host', '127.0.0.1'),
            '--port=' . (string)Arr::get($config, 'port', 5432),
            '--username=' . (string)Arr::get($config, 'username', ''),
            (string)Arr::get($config, 'database', ''),
        ], fn($v) => $v !== '');

        return implode(' ', array_map('escapeshellarg', $parts));
    }

    /**
     * Check if a file is gzip compressed based on extension
     */
    protected function isGzipCompressed(string $path): bool
    {
        return str_ends_with($path, '.gz') || str_ends_with($path, '.gzip');
    }
}
