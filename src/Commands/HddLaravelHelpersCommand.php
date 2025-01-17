<?php

namespace HassanDomeDenea\HddLaravelHelpers\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'hddlaravelhelpers')]
class HddLaravelHelpersCommand extends Command
{
    public $signature = 'hddlaravelhelpers';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
