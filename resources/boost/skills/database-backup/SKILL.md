---
name: database-backup
description: Database backup and restore with gzip compression, multi-driver support, and Telegram integration.
---

# Database Backup & Restore

## When to use this skill
Use this skill when implementing database backup/restore workflows, configuring backup compression, or setting up automated Telegram backup notifications.

## Configuration

In `config/hdd-laravel-helpers.php`:

```php
'database-backup' => [
    'gzip_compress' => env('HDD_DATABASE_BACKUP_GZIP_COMPRESS', true),
    'mysqldump_binary' => env('HDD_DATABASE_BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_client_binary' => env('HDD_DATABASE_BACKUP_MYSQL_CLIENT_BINARY', 'mysql'),
    'gzip_binary' => env('HDD_DATABASE_BACKUP_GZIP_BINARY', 'gzip'),
],
```

Environment variables:

```env
HDD_DATABASE_BACKUP_GZIP_COMPRESS=true
HDD_DATABASE_BACKUP_MYSQLDUMP_BINARY=mysqldump
HDD_DATABASE_BACKUP_MYSQL_CLIENT_BINARY=mysql
HDD_DATABASE_BACKUP_GZIP_BINARY=gzip
```

## Usage

```php
use HassanDomeDenea\HddLaravelHelpers\Services\DatabaseBackupManager;

$manager = new DatabaseBackupManager();

// Create backup (returns file path)
$backupPath = $manager->backup('local');  // Uses Storage disk name

// Restore from backup
$manager->restore($backupPath);

// Restore and run migrations after
$manager->restore($backupPath, migrate: true);
```

## Supported Database Drivers

| Driver | Backup Tool | Restore Tool |
|--------|------------|--------------|
| MySQL / MariaDB | `mysqldump` | `mysql` client |
| SQLite | File copy | File copy |
| PostgreSQL | `pg_dump` | `psql` |

### MySQL/MariaDB
Uses `mysqldump` with `--single-transaction --quick --skip-lock-tables` flags.
Password is passed via `MYSQL_PWD` environment variable (not on command line).

### SQLite
Simple file copy of the database file.

### PostgreSQL
Uses `pg_dump` for backup and `psql` for restore.
Password is passed via `PGPASSWORD` environment variable.

## Gzip Compression

When `gzip_compress` is enabled (default), backups are compressed after creation:
- Backup file gets `.gz` extension appended
- On restore, gzip detection uses file signature bytes (`\x1F\x8B`), not file extension
- This means renamed files are still correctly detected as gzip

## Backup File Naming

Files are named: `{app-name}_{connection}_{timestamp}.{ext}`

Example: `my-app_mysql_20260307_143022.sql.gz`

## Telegram Integration

Send backups to a Telegram channel automatically:

### Configuration

```env
HDD_TELEGRAM_BOT_TOKEN=your-bot-token
HDD_TELEGRAM_BACKUP_CHAT_ID=your-chat-id
```

In `config/hdd-laravel-helpers.php`:

```php
'telegram' => [
    'bot_token' => env('HDD_TELEGRAM_BOT_TOKEN'),
    'backup_chat_id' => env('HDD_TELEGRAM_BACKUP_CHAT_ID'),
],
```

### Artisan Command

```bash
php artisan backup:telegram
```

This runs `BackupDatabaseToTelegramCommand` which creates a backup and sends it to the configured Telegram chat.

### Scheduling

```php
// In app/Console/Kernel.php or routes/console.php
Schedule::command('backup:telegram')->daily();
```

## Complete Example

```php
use HassanDomeDenea\HddLaravelHelpers\Services\DatabaseBackupManager;
use Illuminate\Support\Facades\Storage;

// Backup workflow
$manager = new DatabaseBackupManager();

// Create backup on local disk
$backupPath = $manager->backup('local');

// Copy to S3 for offsite storage
$filename = basename($backupPath);
Storage::disk('s3')->put(
    "backups/{$filename}",
    file_get_contents($backupPath)
);

// Restore workflow
$manager->restore($backupPath, migrate: true);
```
