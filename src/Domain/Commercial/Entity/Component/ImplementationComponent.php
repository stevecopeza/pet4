<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

class ImplementationComponent extends QuoteComponent
{
    /** @var QuoteMilestone[] */
    private array $milestones;

    public function __construct(
        array $milestones = [],
        ?string $description = null,
        ?int $id = null,
        string $section = 'General'
    ) {
        parent::__construct('implementation', $description, $id, $section);
        $this->milestones = $milestones;
    }

    /** @return QuoteMilestone[] */
    public function milestones(): array
    {
        return $this->milestones;
    }

    public function addMilestone(QuoteMilestone $milestone): void
    {
        $this->milestones[] = $milestone;
    }

    public function sellValue(): float
    {
        $total = 0.0;
        foreach ($this->milestones as $milestone) {
            $total += $milestone->sellValue();
        }
        return $total;
    }

    public function internalCost(): float
    {
        $total = 0.0;
        foreach ($this->milestones as $milestone) {
            $total += $milestone->internalCost();
        }
        return $total;
    }
}
