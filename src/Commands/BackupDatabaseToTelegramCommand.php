<?php

namespace HassanDomeDenea\HddLaravelHelpers\Commands;

use HassanDomeDenea\HddLaravelHelpers\Telegram\HDDTelegramService;
use Illuminate\Console\Command;

class BackupDatabaseToTelegramCommand extends Command
{

    protected $signature = 'backup:telegram';


    protected $description = 'Create database backup and send it to Telegram channel';

    public function handle(): void
    {
        HDDTelegramService::SendDatabaseBackup();
        $this->info('Database backup sent to Telegram successfully.');

    }
}
