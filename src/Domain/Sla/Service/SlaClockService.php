<?php

declare(strict_types=1);

namespace Pet\Domain\Sla\Service;

use Pet\Domain\Sla\Entity\SlaSnapshot;
use Pet\Domain\Calendar\Service\BusinessTimeCalculator;

class SlaClockService
{
    private BusinessTimeCalculator $timeCalculator;

    public function __construct(BusinessTimeCalculator $timeCalculator)
    {
        $this->timeCalculator = $timeCalculator;
    }

    /**
     * Calculates the due date for an SLA target (Response or Resolution)
     * 
     * NOTE: This is the INVERSE of calculateBusinessMinutes.
     * We need to add X business minutes to a start time to find the end time.
     * 
     * This is a complex calculation (finding the date where business_minutes(start, date) >= target).
     * For MVP/Skeleton, we can implement a simplified "add minutes" logic that respects windows.
     */
    public function calculateDueDate(
        \DateTimeImmutable $startTime,
        int $targetMinutes,
        SlaSnapshot $snapshot
    ): \DateTimeImmutable {
        return $this->timeCalculator->addBusinessMinutes(
            $startTime,
            $targetMinutes,
            $snapshot->calendarSnapshot()
        );
    }

    /**
     * Calculates the current SLA clock usage (e.g., "75% of response time used")
     */
    public function calculateUsage(
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $currentTime,
        int $targetMinutes,
        SlaSnapshot $snapshot
    ): float {
        $usedMinutes = $this->timeCalculator->calculateBusinessMinutes(
            $startTime,
            $currentTime,
            $snapshot->calendarSnapshot()
        );

        if ($targetMinutes === 0) {
            return 100.0;
        }

        return round(($usedMinutes / $targetMinutes) * 100, 2);
    }
}
