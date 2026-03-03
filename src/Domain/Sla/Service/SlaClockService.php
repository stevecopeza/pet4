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
     * Calculates the due date for an SLA target (Response or Resolution).
     * For single-tier SLAs, uses the snapshot's calendar directly.
     * For tiered SLAs, use calculateTieredDueDate() instead.
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
     * Calculates the due date for a tiered SLA using the active tier's calendar.
     *
     * @param \DateTimeImmutable $startTime When the clock started (or tier transition time)
     * @param int $remainingMinutes Minutes remaining in this tier
     * @param array $tierCalendarSnapshot The active tier's calendar snapshot
     */
    public function calculateTieredDueDate(
        \DateTimeImmutable $startTime,
        int $remainingMinutes,
        array $tierCalendarSnapshot
    ): \DateTimeImmutable {
        return $this->timeCalculator->addBusinessMinutes(
            $startTime,
            $remainingMinutes,
            $tierCalendarSnapshot
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

    /**
     * Calculates tier-aware usage using the active tier's calendar and target.
     *
     * @param \DateTimeImmutable $startTime When the tier became active
     * @param \DateTimeImmutable $currentTime Now
     * @param int $targetMinutes The active tier's target
     * @param array $tierCalendarSnapshot The active tier's calendar snapshot
     * @param float $carriedForwardPercent Percentage carried from a previous tier (0 if first tier)
     */
    public function calculateTieredUsage(
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $currentTime,
        int $targetMinutes,
        array $tierCalendarSnapshot,
        float $carriedForwardPercent = 0.0
    ): float {
        $usedMinutes = $this->timeCalculator->calculateBusinessMinutes(
            $startTime,
            $currentTime,
            $tierCalendarSnapshot
        );

        if ($targetMinutes === 0) {
            return 100.0;
        }

        $tierPercent = ($usedMinutes / $targetMinutes) * 100;
        return round($carriedForwardPercent + $tierPercent, 2);
    }

    /**
     * Recalculates the due date after a tier transition.
     *
     * @param \DateTimeImmutable $transitionTime When the transition occurred
     * @param int $remainingMinutes Minutes remaining in the new tier
     * @param array $newTierCalendarSnapshot The new tier's calendar snapshot
     */
    public function recalculateDueAfterTransition(
        \DateTimeImmutable $transitionTime,
        int $remainingMinutes,
        array $newTierCalendarSnapshot
    ): \DateTimeImmutable {
        return $this->timeCalculator->addBusinessMinutes(
            $transitionTime,
            $remainingMinutes,
            $newTierCalendarSnapshot
        );
    }

    /**
     * Calculate business minutes elapsed between two times using a specific calendar.
     */
    public function calculateElapsedMinutes(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $calendarSnapshot
    ): int {
        return $this->timeCalculator->calculateBusinessMinutes($start, $end, $calendarSnapshot);
    }
}
