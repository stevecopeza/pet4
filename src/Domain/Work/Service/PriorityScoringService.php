<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Service;

use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Identity\Repository\EmployeeRepository;
use Pet\Domain\Work\Repository\AssignmentRepository;
use DateTimeImmutable;

class PriorityScoringService
{
    private const MAX_SLA_COMPONENT = 500.0;
    private const MAX_DEADLINE_COMPONENT = 250.0;
    private const MAX_ESCALATION_COMPONENT = 150.0;
    private const MAX_SCHEDULE_COMPONENT = 100.0;
    private const MAX_COMMERCIAL_COMPONENT = 100.0;
    private const MAX_MANAGER_OVERRIDE = 300.0;
    private const MAX_ROLE_WEIGHT_COMPONENT = 50.0;
    private const WAITING_PENALTY = -400.0;

    private ?DateTimeImmutable $now;
    private ?EmployeeRepository $employeeRepository;
    private ?AssignmentRepository $assignmentRepository;

    public function __construct(
        ?DateTimeImmutable $now = null,
        ?EmployeeRepository $employeeRepository = null,
        ?AssignmentRepository $assignmentRepository = null
    ) {
        $this->now = $now;
        $this->employeeRepository = $employeeRepository;
        $this->assignmentRepository = $assignmentRepository;
    }

    private function getNow(): DateTimeImmutable
    {
        return $this->now ?? new DateTimeImmutable();
    }

    public function calculate(WorkItem $workItem): float
    {
        $score = 0.0;
        
        $score += $this->calculateSlaComponent($workItem);
        $score += $this->calculateDeadlineComponent($workItem);
        $score += $this->calculateEscalationComponent($workItem);
        $score += $this->calculateScheduleComponent($workItem);
        $score += $this->calculateCommercialComponent($workItem);
        $score += $this->calculateManagerOverride($workItem);
        $score += $this->calculateWaitingPenalty($workItem);
        $score += $this->calculateRoleWeightComponent($workItem);

        return $score;
    }

    private function calculateRoleWeightComponent(WorkItem $workItem): float
    {
        // 1. Check if item has a required role
        $requiredRoleId = $workItem->getRequiredRoleId();
        if ($requiredRoleId === null) {
            return 0.0;
        }

        // 2. Check if item is assigned
        $assignedUserId = $workItem->getAssignedUserId();
        if (!$assignedUserId) {
            return 0.0;
        }

        // 3. Resolve dependencies
        if (!$this->employeeRepository || !$this->assignmentRepository) {
            return 0.0;
        }

        // 4. Find Employee and their Active Assignment
        if (!is_numeric($assignedUserId)) {
            return 0.0; 
        }

        $employee = $this->employeeRepository->findByWpUserId((int)$assignedUserId);
        if (!$employee || !$employee->id()) {
            return 0.0;
        }

        // Find active assignments for employee
        $assignments = $this->assignmentRepository->findByEmployeeId($employee->id());
        
        $now = $this->getNow();
        foreach ($assignments as $assignment) {
            if ($assignment->status() !== 'active') {
                continue;
            }
            if ($assignment->endDate() && $assignment->endDate() < $now) {
                continue;
            }
            if ($assignment->startDate() > $now) {
                continue;
            }

            // Check Role Match
            if ($assignment->roleId() === $requiredRoleId) {
                return self::MAX_ROLE_WEIGHT_COMPONENT;
            }
        }

        return 0.0;
    }

    private function calculateSlaComponent(WorkItem $workItem): float
    {
        $minutes = $workItem->getSlaTimeRemainingMinutes();
        if ($minutes === null) {
            return 0.0;
        }

        if ($minutes < 0) {
            return self::MAX_SLA_COMPONENT; // Breached
        }
        if ($minutes < 60) {
            return 400.0; // Critical (< 1 hour)
        }
        if ($minutes < 240) {
            return 300.0; // High (< 4 hours)
        }
        if ($minutes < 1440) {
            return 100.0; // Medium (< 24 hours)
        }

        return 50.0; // Low
    }

    private function calculateDeadlineComponent(WorkItem $workItem): float
    {
        $due = $workItem->getScheduledDueUtc();
        if ($due === null) {
            return 0.0;
        }

        $now = $this->getNow();
        
        if ($due < $now) {
            return self::MAX_DEADLINE_COMPONENT; // Overdue
        }

        $diff = $due->diff($now);
        $hours = ($diff->days * 24) + $diff->h;

        if ($hours < 24) {
            return 200.0; // Due Today
        }
        if ($diff->days < 3) {
            return 100.0; // Due Soon
        }

        return 0.0;
    }

    private function calculateEscalationComponent(WorkItem $workItem): float
    {
        $level = $workItem->getEscalationLevel();
        if ($level <= 0) {
            return 0.0;
        }

        $score = $level * 50.0;
        return min($score, self::MAX_ESCALATION_COMPONENT);
    }

    private function calculateScheduleComponent(WorkItem $workItem): float
    {
        $start = $workItem->getScheduledStartUtc();
        if ($start === null) {
            return 0.0;
        }

        $now = $this->getNow();
        
        if ($start < $now) {
            return self::MAX_SCHEDULE_COMPONENT; // Should have started
        }

        $diff = $start->diff($now);
        $hours = ($diff->days * 24) + $diff->h;

        if ($hours < 2) {
            return 50.0; // Starting soon
        }

        return 0.0;
    }

    private function calculateCommercialComponent(WorkItem $workItem): float
    {
        $score = 0.0;
        
        // Client Tier Boost (1=Standard, 2=Silver, 3=Gold, 4=Platinum)
        $tier = $workItem->getClientTier();
        if ($tier > 1) {
            $score += ($tier - 1) * 25.0;
        }
        
        // Revenue Boost (High value projects get bump)
        // Assume > 10000 is high value
        $revenue = $workItem->getRevenue();
        if ($revenue > 50000) {
            $score += 25.0;
        } elseif ($revenue > 10000) {
            $score += 10.0;
        }

        return min($score, self::MAX_COMMERCIAL_COMPONENT);
    }

    private function calculateManagerOverride(WorkItem $workItem): float
    {
        $override = $workItem->getManagerPriorityOverride();
        // Clamp between -MAX and +MAX
        if ($override > self::MAX_MANAGER_OVERRIDE) return self::MAX_MANAGER_OVERRIDE;
        if ($override < -self::MAX_MANAGER_OVERRIDE) return -self::MAX_MANAGER_OVERRIDE;
        return $override;
    }

    private function calculateWaitingPenalty(WorkItem $workItem): float
    {
        if ($workItem->getStatus() === 'waiting') {
            return self::WAITING_PENALTY;
        }
        return 0.0;
    }
}
