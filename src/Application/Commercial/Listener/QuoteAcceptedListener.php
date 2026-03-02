<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Listener;

use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Event\ContractCreated;
use Pet\Domain\Commercial\Event\BaselineCreated;
use Pet\Domain\Commercial\Entity\Contract;
use Pet\Domain\Commercial\Entity\Baseline;
use Pet\Domain\Commercial\Repository\ContractRepository;
use Pet\Domain\Commercial\Repository\BaselineRepository;
use Pet\Domain\Commercial\ValueObject\ContractStatus;
use Pet\Domain\Event\EventBus;
use Pet\Domain\Sla\Repository\SlaRepository;
use Pet\Domain\Calendar\Repository\CalendarRepository;
use Pet\Domain\Commercial\Entity\Component\RecurringServiceComponent;
use Pet\Domain\Sla\Entity\SlaSnapshot;
use Pet\Domain\Commercial\Entity\Quote;

class QuoteAcceptedListener
{
    private ContractRepository $contractRepository;
    private BaselineRepository $baselineRepository;
    private EventBus $eventBus;
    private SlaRepository $slaRepository;
    private CalendarRepository $calendarRepository;

    public function __construct(
        ContractRepository $contractRepository,
        BaselineRepository $baselineRepository,
        EventBus $eventBus,
        SlaRepository $slaRepository,
        CalendarRepository $calendarRepository
    ) {
        $this->contractRepository = $contractRepository;
        $this->baselineRepository = $baselineRepository;
        $this->eventBus = $eventBus;
        $this->slaRepository = $slaRepository;
        $this->calendarRepository = $calendarRepository;
    }

    public function __invoke(QuoteAccepted $event): void
    {
        $quote = $event->quote();

        // Idempotency Guard: Do not create contract if it already exists for this quote
        if ($this->contractRepository->findByQuoteId($quote->id())) {
            return;
        }

        // Create Contract
        $contract = new Contract(
            (int)$quote->id(),
            $quote->customerId(),
            ContractStatus::active(),
            $quote->totalValue(),
            $quote->currency() ?? 'USD',
            $quote->acceptedAt() ?? new \DateTimeImmutable()
        );
        
        $this->contractRepository->save($contract);
        $this->eventBus->dispatch(new ContractCreated($contract));
        
        if ($contract->id()) {
            $baseline = new Baseline(
                $contract->id(),
                $quote->totalValue(),
                $quote->totalInternalCost(),
                $quote->components()
            );
            
            $this->baselineRepository->save($baseline);
            $this->eventBus->dispatch(new BaselineCreated($baseline));

            $this->createSlaSnapshot($quote, $contract->id());
        }
    }

    private function createSlaSnapshot(Quote $quote, int $contractId): void
    {
        foreach ($quote->components() as $component) {
            if ($component instanceof RecurringServiceComponent) {
                $slaData = $component->slaSnapshot();
                $slaName = $slaData['name'] ?? 'Standard';

                // Find existing published SLA by name
                $slas = $this->slaRepository->findAll();
                $sla = null;
                foreach ($slas as $s) {
                    if ($s->name() === $slaName && $s->status() === 'published') {
                        $sla = $s;
                        break;
                    }
                }

                if ($sla) {
                    $snapshot = $sla->createSnapshot($contractId);
                    $this->slaRepository->saveSnapshot($snapshot);
                } else {
                    // Fallback: Create snapshot from data + default calendar
                    $calendar = $this->calendarRepository->findDefault();
                    if ($calendar) {
                        $snapshot = new SlaSnapshot(
                            $contractId,
                            0, // No original ID
                            1, // Version 1
                            $slaName,
                            (int)($slaData['response_minutes'] ?? 240),
                            (int)($slaData['resolution_minutes'] ?? 1440),
                            $calendar->createSnapshot()
                        );
                        $this->slaRepository->saveSnapshot($snapshot);
                    }
                }
                
                // Only process first recurring component
                return;
            }
        }
    }
}
