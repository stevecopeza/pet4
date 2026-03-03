<?php

declare(strict_types=1);

namespace Pet\Domain\Sla\Service;

use Pet\Domain\Sla\Entity\SlaSnapshot;
use Pet\Domain\Calendar\Service\BusinessTimeCalculator;

/**
 * Core tier logic: selects the matching tier for a given moment,
 * and calculates carry-forward on boundary crossings.
 */
class TierEvaluator
{
    private BusinessTimeCalculator $timeCalculator;

    public function __construct(BusinessTimeCalculator $timeCalculator)
    {
        $this->timeCalculator = $timeCalculator;
    }

    /**
     * Determine which tier is active at the given moment.
     * Evaluates tiers in priority order (1 = highest priority, evaluated first).
     * The first tier whose calendar considers $now a working moment wins.
     *
     * @param \DateTimeImmutable $now
     * @param array $tierSnapshots Array of tier snapshot data from SlaSnapshot
     * @param array $calendarSnapshotsByTier Keyed by tier priority => calendar snapshot array
     * @return int|null The priority of the matching tier, or null if none match
     */
    public function selectTier(
        \DateTimeImmutable $now,
        array $tierSnapshots,
        array $calendarSnapshotsByTier
    ): ?int {
        // Sort by priority ascending (1 = highest)
        usort($tierSnapshots, fn($a, $b) => ($a['priority'] ?? 0) <=> ($b['priority'] ?? 0));

        foreach ($tierSnapshots as $tier) {
            $priority = $tier['priority'] ?? 0;
            $calendarSnapshot = $calendarSnapshotsByTier[$priority] ?? null;

            if ($calendarSnapshot === null) {
                continue;
            }

            if ($this->isWorkingMoment($now, $calendarSnapshot)) {
                return $priority;
            }
        }

        return null;
    }

    /**
     * Calculate carry-forward when transitioning between tiers.
     *
     * @param int $elapsedBusinessMinutes Minutes elapsed in the current tier
     * @param int $currentTierTargetMinutes The target for the current tier (response or resolution)
     * @param int $newTierTargetMinutes The target for the new tier
     * @param int $capPercent The maximum carry-forward percentage (1-99)
     * @return array{carried_percent: float, equivalent_elapsed: int, remaining_minutes: int}
     */
    public function calculateTransition(
        int $elapsedBusinessMinutes,
        int $currentTierTargetMinutes,
        int $newTierTargetMinutes,
        int $capPercent
    ): array {
        // Calculate actual percentage consumed in current tier
        $actualPercent = $currentTierTargetMinutes > 0
            ? ($elapsedBusinessMinutes / $currentTierTargetMinutes) * 100
            : 100.0;

        // Apply the cap
        $carriedPercent = min($actualPercent, (float)$capPercent);

        // Convert to equivalent elapsed time in the new tier
        $equivalentElapsed = (int)round(($carriedPercent / 100) * $newTierTargetMinutes);

        // Remaining time in the new tier
        $remainingMinutes = max(0, $newTierTargetMinutes - $equivalentElapsed);

        return [
            'carried_percent' => round($carriedPercent, 2),
            'equivalent_elapsed' => $equivalentElapsed,
            'remaining_minutes' => $remainingMinutes,
        ];
    }

    /**
     * Check if the given moment falls within a working window for the calendar.
     */
    private function isWorkingMoment(\DateTimeImmutable $now, array $calendarSnapshot): bool
    {
        $timezone = new \DateTimeZone($calendarSnapshot['timezone'] ?? 'UTC');
        $local = $now->setTimezone($timezone);

        $currentDate = $local->format('Y-m-d');
        $currentDay = strtolower($local->format('l'));
        $currentTime = $local->format('H:i');

        // Check holidays
        $holidays = $calendarSnapshot['holidays'] ?? [];
        foreach ($holidays as $h) {
            if (!empty($h['is_recurring'])) {
                if (substr($h['date'], 5) === substr($currentDate, 5)) {
                    return false;
                }
            } else {
                if (($h['date'] ?? '') === $currentDate) {
                    return false;
                }
            }
        }

        // Check working windows
        $windows = $calendarSnapshot['working_windows'] ?? [];
        foreach ($windows as $w) {
            if (strtolower($w['day_of_week'] ?? '') !== $currentDay) {
                continue;
            }
            $start = $w['start_time'] ?? '00:00';
            $end = $w['end_time'] ?? '00:00';
            if ($currentTime >= $start && $currentTime < $end) {
                return true;
            }
        }

        return false;
    }
}
