<?php

declare(strict_types=1);

namespace Pet\Application\Work\Cron;

use Pet\Domain\Work\Service\SlaClockCalculator;
use Pet\Application\System\Service\FeatureFlagService;

class WorkItemPriorityUpdateJob
{
    public function __construct(
        private SlaClockCalculator $calculator,
        private FeatureFlagService $featureFlags
    ) {}

    public function run(): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[PET PriorityEngine] Starting priority update job...');
        }

        if (!$this->featureFlags->isPriorityEngineEnabled()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PET PriorityEngine] Skipped: Priority Engine disabled');
            }
            return;
        }

        try {
            $count = $this->calculator->recalculateAllActive();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[PET PriorityEngine] Recalculated priority for %d items', $count));
            }
        } catch (\Throwable $e) {
            error_log('[PET PriorityEngine] Update Failed: ' . $e->getMessage());
        }
    }
}
