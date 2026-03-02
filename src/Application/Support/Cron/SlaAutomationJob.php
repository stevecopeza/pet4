<?php

declare(strict_types=1);

namespace Pet\Application\Support\Cron;

use Pet\Application\Support\Service\SlaCheckService;

class SlaAutomationJob
{
    private SlaCheckService $slaCheck;

    public function __construct(SlaCheckService $slaCheck)
    {
        $this->slaCheck = $slaCheck;
    }

    public function run(): void
    {
        $this->slaCheck->runSlaCheck();
    }
}
