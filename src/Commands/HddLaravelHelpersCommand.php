<?php

namespace HassanDomeDenea\HddLaravelHelpers\Commands;

use Illuminate\Console\Command;

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
