<?php

namespace HassanDomeDenea\HddLaravelHelpers\Telegram;

use Exception;
use File;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Log;
use Throwable;

class HDDTelegramService
{
    public static function SendErrorMessage(Throwable $exception): void
    {

        $token = config('hdd-laravel-helpers.telegram.bot_token');
        $chatId = config('hdd-laravel-helpers.telegram.errors_chat_id');

        $text = $exception->getMessage();
        $trace = str($exception->getTraceAsString())->take(200)->toString();
        try {
            Http::timeout(5)
                ->get("https://api.telegram.org/bot$token/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => "Error from " . config('app.name') . ": \n\n" . $text . "\n\nTrace:\n" . $trace
                ]);
        } catch (Exception) {
            Log::info("Telegram Error In Sending Error:" . $exception->getMessage());
            // Ignore telegram sending errors
        }

    }

    public static function SendDatabaseBackup(): void
    {
        $token = config('hdd-laravel-helpers.telegram.bot_token');
        $chatId = config('hdd-laravel-helpers.telegram.backup_chat_id');
        if (blank($token) || blank($chatId)) {
            return;
        }
        $dbConnection = DB::connection();
        if ($dbConnection->getDriverName() === 'sqlite') {
            $databasePath = $dbConnection->getDatabaseName();
            $backupPath = storage_path('app/backup_' . now()->format('Y-m-d_H-i-s') . '.sqlite');

            File::copy($databasePath, $backupPath);

            $client = new Client;

            $client->post("https://api.telegram.org/bot{$token}/sendDocument", [
                'multipart' => [
                    [
                        'name' => 'chat_id',
                        'contents' => $chatId,
                    ],
                    [
                        'name' => 'document',
                        'contents' => fopen($backupPath, 'r'),
                        'filename' => config('app.name') . '_database_backup_' . date('Y-m-d_H-i-s') . '.sqlite',
                    ],
                ],
            ]);

            File::delete($backupPath);
        } else if ($dbConnection->getDriverName() === 'mysql' || $dbConnection->getDriverName() === 'mariadb') {
            // Build backup filename
            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupPath = storage_path("app/backup_$timestamp.sql.gz");

            $dbHost = $dbConnection->getConfig('host');
            $dbPort = $dbConnection->getConfig('port');
            $dbName = $dbConnection->getConfig('database');
            $dbUser = $dbConnection->getConfig('username');
            $dbPass = $dbConnection->getConfig('password');
            $socket = $dbConnection->getConfig('unix_socket') ?: null;

            // Build mysqldump command. We use escapeshellarg for safety.
            $parts = [];
            $parts[] = 'mysqldump';
            if ($socket) {
                $parts[] = '--socket=' . escapeshellarg($socket);
            } else {
                $parts[] = '--host=' . escapeshellarg($dbHost);
                if ($dbPort) {
                    $parts[] = '--port=' . escapeshellarg($dbPort);
                }
            }

            $parts[] = '--user=' . escapeshellarg($dbUser);

            // Pass password option without space as required by mysqldump when provided inline
            if ($dbPass !== null && $dbPass !== '') {
                $parts[] = '--password=' . escapeshellarg($dbPass);
            }

            // Add additional options to get consistent dump
            $parts[] = '--single-transaction';
            $parts[] = '--quick';
            $parts[] = '--skip-lock-tables';
            $parts[] = escapeshellarg($dbName);

            // Create a directory if needed
            $dir = dirname($backupPath);
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            // Final command: mysqldump ... | gzip > backup.sql.gz
            $cmd = implode(' ', $parts) . " | gzip > " . escapeshellarg($backupPath);

            try {
                // Execute the command and capture exit code
                $output = null;
                $returnVar = null;
                exec($cmd . ' 2>&1', $output, $returnVar);

                if ($returnVar !== 0 || !File::exists($backupPath)) {
                    // Dump failed
                    $message = "Database dump failed. Command: $cmd. ExitCode: {$returnVar}. Output: " . implode("\n", (array)$output);
                    Log::error($message);
                    return;
                }

                // Send the generated gzipped SQL file to Telegram
                $client = new Client;

                $client->post("https://api.telegram.org/bot$token/sendDocument", [
                    'multipart' => [
                        [
                            'name' => 'chat_id',
                            'contents' => $chatId,
                        ],
                        [
                            'name' => 'document',
                            'contents' => fopen($backupPath, 'r'),
                            'filename' => config('app.name') . "_database_backup_$timestamp.sql.gz",
                        ],
                    ],
                ]);

            } catch (Throwable $e) {
                Log::error("Database backup/send failed: " . $e->getMessage());
            } finally {
                // Clean up a local backup file if it exists
                try {
                    if (File::exists($backupPath)) {
                        File::delete($backupPath);
                    }
                } catch (Throwable) {
                    // ignore cleanup errors
                }
            }
        }
    }
}
