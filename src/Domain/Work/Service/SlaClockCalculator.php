<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Service;

use Pet\Domain\Work\Entity\WorkItem;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\Domain\Advisory\Entity\AdvisorySignal;
use DateTimeImmutable;

class SlaClockCalculator
{
    public function __construct(
        private WorkItemRepository $repository,
        private PriorityScoringService $scoringService,
        private AdvisorySignalRepository $signalRepository
    ) {}

    public function recalculateAllActive(): int
    {
        $items = $this->repository->findActive();
        $updatedCount = 0;
        $now = new DateTimeImmutable();

        // Pass 1: Calculate Load per Department
        $deptCounts = [];
        foreach ($items as $item) {
            $deptId = $item->getDepartmentId();
            $deptCounts[$deptId] = ($deptCounts[$deptId] ?? 0) + 1;
        }

        foreach ($items as $item) {
            $this->updateItemSlaState($item, $now, $deptCounts);
            $this->repository->save($item);
            $updatedCount++;
        }

        return $updatedCount;
    }

    public function updateItemSlaState(WorkItem $item, DateTimeImmutable $now, array $deptCounts = []): void
    {
        // Clear existing signals for this cycle (if this is run periodically)
        // Note: If called from Projector, we might not want to clear signals immediately, but for now it's safe as Projector creates new items mostly.
        // Actually, Projector calls this on CREATE, so no signals exist.
        // But if called on UPDATE, we might want to clear.
        // Let's keep it safe.
        $this->signalRepository->clearForWorkItem($item->getId());

        // Update SLA time remaining if due date is present
        $due = $item->getScheduledDueUtc();
        if ($due) {
            // Calculate remaining minutes
            $diff = $now->diff($due);
            $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
            
            if ($due < $now) {
                $minutes = -$minutes;
            }

            $item->updateSlaState($item->getSlaSnapshotId(), $minutes);

            // Generate Advisory Signals based on time remaining
            if ($item->getSlaSnapshotId()) {
                // SLA Logic
                if ($minutes < 0) {
                    $this->createSignal($item->getId(), AdvisorySignal::TYPE_SLA_RISK, AdvisorySignal::SEVERITY_CRITICAL, "SLA Breached by " . abs($minutes) . " minutes");
                } elseif ($minutes < 60) {
                    $this->createSignal($item->getId(), AdvisorySignal::TYPE_SLA_RISK, AdvisorySignal::SEVERITY_WARNING, "SLA Risk: Less than 60 minutes remaining");
                }
            } else {
                // Standard Deadline Logic
                if ($minutes < 0) {
                    $this->createSignal($item->getId(), AdvisorySignal::TYPE_DEADLINE_RISK, AdvisorySignal::SEVERITY_CRITICAL, "Deadline Overdue by " . abs($minutes) . " minutes");
                } elseif ($minutes < 1440) { // 24 hours
                    $this->createSignal($item->getId(), AdvisorySignal::TYPE_DEADLINE_RISK, AdvisorySignal::SEVERITY_WARNING, "Deadline approaching (less than 24 hours)");
                }
            }
        }

        // Recalculate Score
        $newScore = $this->scoringService->calculate($item);
        $item->updatePriorityScore($newScore);
        
        // High Priority Idle Check
        if ($newScore > 80 && $item->getStatus() === 'waiting') {
             $this->createSignal($item->getId(), AdvisorySignal::TYPE_IDLE_HIGH_PRIORITY, AdvisorySignal::SEVERITY_WARNING, "High priority item is idle");
        }

        // Capacity Bottleneck Check (Threshold: > 10 active items in department)
        // Only flag if item is waiting (stuck in queue)
        if (($deptCounts[$item->getDepartmentId()] ?? 0) > 10 && $item->getStatus() === 'waiting') {
            $this->createSignal($item->getId(), AdvisorySignal::TYPE_CAPACITY_BOTTLENECK, AdvisorySignal::SEVERITY_WARNING, "Department Overloaded: " . $deptCounts[$item->getDepartmentId()] . " active items");
        }
    }

    private function createSignal(string $workItemId, string $type, string $severity, string $message): void
    {
        $signal = new AdvisorySignal(
            wp_generate_uuid4(),
            $workItemId,
            $type,
            $severity,
            $message,
            new DateTimeImmutable()
        );
        $this->signalRepository->save($signal);
    }
}
