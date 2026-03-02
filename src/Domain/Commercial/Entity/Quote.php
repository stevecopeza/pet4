<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

use Pet\Domain\Commercial\Entity\Component\QuoteComponent;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;

class Quote
{
    private ?int $id;
    private int $customerId;
    private string $title;
    private ?string $description;
    private QuoteState $state;
    private int $version;
    private float $totalValue;
    private float $totalInternalCost;
    private ?string $currency;
    private ?\DateTimeImmutable $acceptedAt;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    /**
     * @var QuoteComponent[]
     */
    private array $components = [];

    /**
     * @var CostAdjustment[]
     */
    private array $costAdjustments = [];

    /**
     * @var PaymentMilestone[]
     */
    private array $paymentSchedule = [];

    private ?\DateTimeImmutable $archivedAt;
    private array $malleableData;

    public function __construct(
        int $customerId,
        string $title,
        ?string $description,
        QuoteState $state,
        int $version = 1,
        float $totalValue = 0.00,
        float $totalInternalCost = 0.00,
        ?string $currency = 'USD',
        ?\DateTimeImmutable $acceptedAt = null,
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $archivedAt = null,
        array $components = [],
        array $malleableData = [],
        array $costAdjustments = [],
        array $paymentSchedule = []
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->title = $title;
        $this->description = $description;
        $this->state = $state;
        $this->version = $version;
        $this->totalValue = $totalValue;
        $this->totalInternalCost = $totalInternalCost;
        $this->currency = $currency;
        $this->acceptedAt = $acceptedAt;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
        $this->archivedAt = $archivedAt;
        $this->components = $components;
        $this->malleableData = $malleableData;
        $this->costAdjustments = $costAdjustments;
        $this->paymentSchedule = $paymentSchedule;
    }
    
    public function costAdjustments(): array
    {
        return $this->costAdjustments;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }

    public function addCostAdjustment(CostAdjustment $adjustment): void
    {
        $this->costAdjustments[] = $adjustment;
    }

    public function totalAdjustments(): float
    {
        $total = 0.0;
        foreach ($this->costAdjustments as $adjustment) {
            $total += $adjustment->amount();
        }
        return $total;
    }

    public function adjustedTotalInternalCost(): float
    {
        return $this->totalInternalCost + $this->totalAdjustments();
    }

    public function margin(): float
    {
        return $this->totalValue - $this->adjustedTotalInternalCost();
    }


    public function id(): ?int
    {
        return $this->id;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function state(): QuoteState
    {
        return $this->state;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function totalValue(): float
    {
        return $this->totalValue;
    }

    public function currency(): ?string
    {
        return $this->currency;
    }

    public function acceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }
    
    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function totalInternalCost(): float
    {
        return $this->totalInternalCost;
    }

    /**
     * @return QuoteComponent[]
     */
    public function components(): array
    {
        return $this->components;
    }

    public function addComponent(QuoteComponent $component): void
    {
        if ($this->state->isTerminal()) {
            throw new \DomainException('Cannot add components to a finalized quote.');
        }
        $this->components[] = $component;
        $this->recalculateTotals();
    }

    public function removeComponent(int $componentId): void
    {
        if ($this->state->isTerminal()) {
            throw new \DomainException('Cannot remove components from a finalized quote.');
        }
        $found = false;
        foreach ($this->components as $key => $component) {
            if ($component->id() === $componentId) {
                unset($this->components[$key]);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            throw new \DomainException("Component not found: {$componentId}");
        }
        
        $this->components = array_values($this->components);
        $this->recalculateTotals();
    }

    private function recalculateTotals(): void
    {
        $this->totalValue = 0.0;
        $this->totalInternalCost = 0.0;
        foreach ($this->components as $component) {
            $this->totalValue += $component->sellValue();
            $this->totalInternalCost += $component->internalCost();
        }
    }

    public function paymentSchedule(): array
    {
        return $this->paymentSchedule;
    }

    public function setPaymentSchedule(array $milestones): void
    {
        foreach ($milestones as $milestone) {
            if (!$milestone instanceof PaymentMilestone) {
                throw new \InvalidArgumentException('Items must be instances of PaymentMilestone');
            }
        }
        $this->paymentSchedule = $milestones;
    }

    public function validate(): void
    {
        $this->validateReadiness();
    }

    public function validateReadiness(): void
    {
        if (empty($this->components)) {
            throw new \DomainException('Quote must have at least one component before it can be sent.');
        }

        foreach ($this->components as $component) {
            if ($component instanceof ImplementationComponent) {
                if (empty($component->milestones())) {
                     throw new \DomainException("Implementation component '{$component->description()}' must have milestones (WBS).");
                }
                foreach ($component->milestones() as $milestone) {
                    if (empty($milestone->tasks())) {
                         throw new \DomainException("Milestone '{$milestone->title()}' must have tasks.");
                    }
                }
            } elseif ($component instanceof CatalogComponent) {
                foreach ($component->items() as $item) {
                    if ($item->type() === 'product' && !empty($item->wbsSnapshot())) {
                        throw new \DomainException("Product item '{$item->description()}' cannot have service-only fields (WBS).");
                    }
                    if (empty($item->sku())) {
                         throw new \DomainException("Catalog item '{$item->description()}' must have an explicit SKU.");
                    }
                    if ($item->type() === 'service' && $item->roleId() === null) {
                         throw new \DomainException("Service item '{$item->description()}' must have an explicit Role ID.");
                    }
                }
            }
        }

        if ($this->margin() < 0) {
            throw new \DomainException('Quote margin cannot be negative.');
        }

        if (empty($this->title)) {
             throw new \DomainException('Quote must have a title.');
        }

        if (empty($this->paymentSchedule)) {
            throw new \DomainException('Quote must have a payment schedule.');
        }

        $paymentTotal = 0.0;
        foreach ($this->paymentSchedule as $milestone) {
            $paymentTotal += $milestone->amount();
        }

        if (abs($paymentTotal - $this->totalValue) > 0.01) {
             throw new \DomainException(sprintf(
                'Payment schedule total (%.2f) must match quote total value (%.2f).',
                $paymentTotal,
                $this->totalValue
            ));
        }
    }

    public function send(): void
    {
        $this->validateReadiness();
        $this->transitionTo(QuoteState::sent());
    }

    public function update(
        int $customerId, 
        string $currency,
        ?\DateTimeImmutable $acceptedAt,
        array $malleableData = []
    ): void {
        if ($this->state->toString() !== QuoteState::draft()->toString()) {
            throw new \DomainException('Cannot update a quote that is not in draft state.');
        }
        $this->customerId = $customerId;
        $this->currency = $currency;
        $this->acceptedAt = $acceptedAt;
        $this->malleableData = $malleableData;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function accept(): void
    {
        $this->validateReadiness();
        $this->transitionTo(QuoteState::accepted());
        $this->acceptedAt = new \DateTimeImmutable();
    }

    public function reject(): void
    {
        $this->transitionTo(QuoteState::rejected());
    }

    public function archive(): void
    {
        $this->archivedAt = new \DateTimeImmutable();
    }

    private function transitionTo(QuoteState $newState): void
    {
        if (!$this->state->canTransitionTo($newState)) {
            throw new \DomainException(sprintf(
                'Invalid state transition from %s to %s',
                $this->state->toString(),
                $newState->toString()
            ));
        }

        $this->state = $newState;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
