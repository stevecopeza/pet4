<?php

declare(strict_types=1);

namespace Pet\Domain\Advisory\Service;

use Pet\Domain\Advisory\Entity\AdvisorySignal;
use Pet\Domain\Advisory\Repository\AdvisorySignalRepository;
use Pet\Domain\Work\Repository\WorkItemRepository;
use Pet\Domain\Work\Service\CapacityCalendar;
use DateTimeImmutable;

class AdvisoryGenerator
{
    private const MAX_ACTIVE_ITEMS = 3;
    private const SLA_RISK_THRESHOLD_MINUTES = 240; // 4 hours
    private const DEADLINE_RISK_HOURS = 24;

    public function __construct(
        private AdvisorySignalRepository $signalRepository,
        private WorkItemRepository $workItemRepository,
        private CapacityCalendar $capacityCalendar
    ) {}

    public function generateForUser(string $userId): void
    {
        $generationRunId = wp_generate_uuid4();
        // 1. Get all active items for the user
        $allItems = $this->workItemRepository->findByAssignedUser($userId);
        $activeItems = array_filter($allItems, fn($item) => $item->getStatus() === 'active');
        
        // 2. Deactivate existing ACTIVE signals for these items (additive history)
        foreach ($activeItems as $item) {
            $this->signalRepository->clearForWorkItem($item->getId(), $generationRunId);
        }

        // 3. Check Context Switching
        $isContextSwitching = count($activeItems) > self::MAX_ACTIVE_ITEMS;
        if ($isContextSwitching) {
            foreach ($activeItems as $item) {
                $this->createSignal(
                    $item->getId(),
                    AdvisorySignal::TYPE_CONTEXT_SWITCHING,
                    AdvisorySignal::SEVERITY_WARNING,
                    sprintf('Context Switching Risk: User has %d active items (Max %d)', count($activeItems), self::MAX_ACTIVE_ITEMS),
                    $generationRunId
                );
            }
        }

        // 4. Check Capacity Bottleneck
        $now = new DateTimeImmutable();
        $nextWeek = $now->modify('+7 days');
        $utilization = $this->capacityCalendar->getUserUtilization($userId, $now, $nextWeek);
        
        if ($utilization > 100.0) {
            foreach ($activeItems as $item) {
                // Only flag items scheduled in this window? 
                // For simplicity, flag all active items as they contribute to load.
                $this->createSignal(
                    $item->getId(),
                    AdvisorySignal::TYPE_CAPACITY_BOTTLENECK,
                    AdvisorySignal::SEVERITY_CRITICAL,
                    sprintf('Capacity Bottleneck: Utilization is %.1f%%', $utilization),
                    $generationRunId
                );
            }
        }

        // 5. Check Item-Specific Risks (SLA, Deadline)
        foreach ($activeItems as $item) {
            $this->checkItemRisks($item, $generationRunId);
        }
    }

    private function checkItemRisks($item, string $generationRunId): void
    {
        // SLA Risk
        $slaMinutes = $item->getSlaTimeRemainingMinutes();
        if ($slaMinutes !== null && $slaMinutes < self::SLA_RISK_THRESHOLD_MINUTES) {
            $severity = ($slaMinutes < 0) ? AdvisorySignal::SEVERITY_CRITICAL : AdvisorySignal::SEVERITY_WARNING;
            $message = ($slaMinutes < 0) 
                ? 'SLA Breached' 
                : sprintf('SLA Risk: Only %d minutes remaining', $slaMinutes);
            
            $this->createSignal(
                $item->getId(),
                AdvisorySignal::TYPE_SLA_RISK,
                $severity,
                $message,
                $generationRunId
            );
        }

        // Deadline Risk
        $due = $item->getScheduledDueUtc();
        if ($due !== null) {
            $now = new DateTimeImmutable();
            if ($due < $now) {
                 $this->createSignal(
                    $item->getId(),
                    AdvisorySignal::TYPE_DEADLINE_RISK,
                    AdvisorySignal::SEVERITY_CRITICAL,
                    'Deadline missed',
                    $generationRunId
                );
            } else {
                $hours = ($due->getTimestamp() - $now->getTimestamp()) / 3600;
                if ($hours < self::DEADLINE_RISK_HOURS) {
                    $this->createSignal(
                        $item->getId(),
                        AdvisorySignal::TYPE_DEADLINE_RISK,
                        AdvisorySignal::SEVERITY_WARNING,
                        sprintf('Deadline Risk: Due in %.1f hours', $hours),
                        $generationRunId
                    );
                }
            }
        }
    }

    private function createSignal(string $workItemId, string $type, string $severity, string $message, string $generationRunId): void
    {
        $signal = new AdvisorySignal(
            $this->generateId(),
            $workItemId,
            $type,
            $severity,
            $message,
            new DateTimeImmutable(),
            'ACTIVE',
            null,
            $generationRunId
        );
        $this->signalRepository->save($signal);
    }

    private function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
