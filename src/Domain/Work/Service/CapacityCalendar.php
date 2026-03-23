<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Service;

use Pet\Domain\Calendar\Repository\CalendarRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Calendar\Service\BusinessTimeCalculator;
use DateTimeImmutable;

class CapacityCalendar
{
    public function __construct(
        private CalendarRepository $calendarRepository,
        private WorkItemRepository $workItemRepository,
        private EmployeeRepository $employeeRepository,
        private BusinessTimeCalculator $timeCalculator,
        private ?\Pet\Domain\Work\Repository\LeaveRequestRepository $leaveRequests = null,
        private ?\Pet\Domain\Work\Repository\CapacityOverrideRepository $capacityOverrides = null
    ) {}

    public function getUserUtilization(
        string $userId,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): float {
        // 1. Calculate Available Capacity
        $calendar = null;
        
        // Try to find employee-specific calendar
        // Note: userId here is likely the WP User ID or string ID?
        // WorkItem stores assigned_user_id as string (from context).
        // Let's assume userId is string and we need to resolve Employee.
        // Wait, WorkItem::assignedUserId is string.
        // EmployeeRepository has findById(int) and findByWpUserId(int).
        // We might need a way to resolve string userId to Employee if it's a UUID or something.
        // But in PET, assigned_user_id seems to be numeric string? Or 'user-1'?
        // Let's check WorkItem entity or usage.
        
        if (is_numeric($userId)) {
             $employee = $this->employeeRepository->findByWpUserId((int)$userId);
             if ($employee && $employee->calendarId()) {
                 $calendar = $this->calendarRepository->findById($employee->calendarId());
             }
        }

        if (!$calendar) {
            $calendar = $this->calendarRepository->findDefault();
        }

        if (!$calendar) {
            return 0.0;
        }

        $snapshot = $calendar->createSnapshot();
        $availableMinutes = 0.0;
        $cursor = new DateTimeImmutable($start->format('Y-m-d') . ' 00:00:00');
        $windowEnd = new DateTimeImmutable($end->format('Y-m-d') . ' 23:59:59');
        while ($cursor <= $windowEnd) {
            $dayStart = new DateTimeImmutable($cursor->format('Y-m-d') . ' 00:00:00');
            $dayEnd = new DateTimeImmutable($cursor->format('Y-m-d') . ' 23:59:59');
            $dayMinutes = $this->timeCalculator->calculateBusinessMinutes($dayStart, $dayEnd, $snapshot);
            // Apply precedence: Approved Leave → 0; Override → scale; Holiday handled by snapshot=0
            if (is_numeric($userId)) {
                $employee = $this->employeeRepository->findByWpUserId((int)$userId);
                if ($employee) {
                    $dateOnly = new DateTimeImmutable($cursor->format('Y-m-d'));
                    if ($this->leaveRequests && $this->leaveRequests->isApprovedOnDate($employee->id(), $dateOnly)) {
                        $dayMinutes = 0.0;
                    } else {
                        $override = $this->capacityOverrides ? $this->capacityOverrides->findForDate($employee->id(), $dateOnly) : null;
                        if ($override) {
                            $dayMinutes = $dayMinutes * max(0, min(100, $override->capacityPct())) / 100.0;
                        }
                    }
                }
            }
            $availableMinutes += $dayMinutes;
            $cursor = $cursor->modify('+1 day');
        }

        if ($availableMinutes <= 0.0 || !is_finite($availableMinutes)) {
            return 0.0;
        }

        // 2. Calculate Assigned Load
        $items = $this->workItemRepository->findByAssignedUser($userId);
        $assignedMinutes = 0.0;

        foreach ($items as $item) {
            if ($item->getStatus() === 'completed') {
                continue;
            }

            $itemStart = $item->getScheduledStartUtc();
            $itemDue = $item->getScheduledDueUtc();

            if (!$itemStart || !$itemDue) {
                continue; 
            }

            // Check overlap: max(start, itemStart) < min(end, itemDue)
            $overlapStart = ($start > $itemStart) ? $start : $itemStart;
            $overlapEnd = ($end < $itemDue) ? $end : $itemDue;

            if ($overlapEnd > $overlapStart) {
                // Calculate business minutes in overlap
                $minutes = $this->timeCalculator->calculateBusinessMinutes($overlapStart, $overlapEnd, $snapshot);
                
                // Apply allocation %
                $allocation = $item->getCapacityAllocationPercent();
                if ($allocation <= 0.0) {
                    // Default behavior: if scheduled but no allocation set, assume 100%?
                    // Or 0? Let's assume 0 for now to avoid blocking on untracked items.
                    $allocation = 0.0;
                } else {
                    $allocation = $allocation / 100.0;
                }
                
                $assignedMinutes += ($minutes * $allocation);
            }
        }

        $utilization = ($assignedMinutes / $availableMinutes) * 100.0;
        if (!is_finite($utilization) || $utilization < 0.0) {
            return 0.0;
        }
        return $utilization;
    }

    public function getUserDailyUtilization(
        int $employeeId,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $calendar = null;
        $employee = $this->employeeRepository->findById($employeeId);
        if ($employee && $employee->calendarId()) {
            $calendar = $this->calendarRepository->findById($employee->calendarId());
        }
        if (!$calendar) {
            $calendar = $this->calendarRepository->findDefault();
        }
        if (!$calendar) {
            return [];
        }
        $snapshot = $calendar->createSnapshot();
        $items = $this->workItemRepository->findByAssignedUser((string)$employee->wpUserId());
        $out = [];
        $cursor = new DateTimeImmutable($start->format('Y-m-d'));
        $windowEnd = new DateTimeImmutable($end->format('Y-m-d'));
        while ($cursor <= $windowEnd) {
            $dayStart = new DateTimeImmutable($cursor->format('Y-m-d') . ' 00:00:00');
            $dayEnd = new DateTimeImmutable($cursor->format('Y-m-d') . ' 23:59:59');
            $capMinutes = $this->timeCalculator->calculateBusinessMinutes($dayStart, $dayEnd, $snapshot);
            if ($this->leaveRequests && $this->leaveRequests->isApprovedOnDate($employeeId, $cursor)) {
                $capMinutes = 0.0;
            } else {
                $override = $this->capacityOverrides ? $this->capacityOverrides->findForDate($employeeId, $cursor) : null;
                if ($override) {
                    $capMinutes = $capMinutes * max(0, min(100, $override->capacityPct())) / 100.0;
                }
            }
            $schedMinutes = 0.0;
            foreach ($items as $item) {
                if ($item->getStatus() === 'completed') {
                    continue;
                }
                $itemStart = $item->getScheduledStartUtc();
                $itemDue = $item->getScheduledDueUtc();
                if (!$itemStart || !$itemDue) {
                    continue;
                }
                $overlapStart = ($dayStart > $itemStart) ? $dayStart : $itemStart;
                $overlapEnd = ($dayEnd < $itemDue) ? $dayEnd : $itemDue;
                if ($overlapEnd > $overlapStart) {
                    $minutes = $this->timeCalculator->calculateBusinessMinutes($overlapStart, $overlapEnd, $snapshot);
                    $allocation = $item->getCapacityAllocationPercent();
                    $allocation = $allocation > 0 ? ($allocation / 100.0) : 0.0;
                    $schedMinutes += ($minutes * $allocation);
                }
            }
            $util = ($capMinutes > 0.0 && is_finite($capMinutes)) ? (($schedMinutes / $capMinutes) * 100.0) : 0.0;
            if (!is_finite($util) || $util < 0.0) {
                $util = 0.0;
            }
            $out[] = [
                'date' => $cursor->format('Y-m-d'),
                'effective_capacity_minutes' => round($capMinutes, 2),
                'scheduled_minutes' => round($schedMinutes, 2),
                'utilization_pct' => round($util, 2),
            ];
            $cursor = $cursor->modify('+1 day');
        }
        return $out;
    }
}
