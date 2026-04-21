<?php

declare(strict_types=1);

namespace Pet\Domain\Delivery\Entity;

use Pet\Domain\Delivery\ValueObject\ProjectState;

class Project
{
    private ?int $id;
    private int $customerId;
    private ?int $sourceQuoteId;
    private string $name;
    private ProjectState $state;
    private float $soldHours; // Immutable constraint from quote
    private float $soldValue;
    private ?\DateTimeImmutable $startDate;
    private ?\DateTimeImmutable $endDate;
    private ?int $malleableSchemaVersion;
    private array $malleableData;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $archivedAt;

    public function __construct(
        int $customerId,
        string $name,
        float $soldHours,
        ?int $sourceQuoteId = null,
        ?ProjectState $state = null,
        float $soldValue = 0.00,
        ?\DateTimeImmutable $startDate = null,
        ?\DateTimeImmutable $endDate = null,
        ?int $id = null,
        ?int $malleableSchemaVersion = null,
        array $malleableData = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $archivedAt = null
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->sourceQuoteId = $sourceQuoteId;
        $this->name = $name;
        $this->soldHours = $soldHours;
        $this->state = $state ?? ProjectState::intake();
        $this->soldValue = $soldValue;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->malleableSchemaVersion = $malleableSchemaVersion;
        $this->malleableData = $malleableData;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
        $this->archivedAt = $archivedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function soldHours(): float
    {
        return $this->soldHours;
    }

    public function soldValue(): float
    {
        return $this->soldValue;
    }

    public function startDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function state(): ProjectState
    {
        return $this->state;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function sourceQuoteId(): ?int
    {
        return $this->sourceQuoteId;
    }

    public function malleableSchemaVersion(): ?int
    {
        return $this->malleableSchemaVersion;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
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

    /**
     * Validate and perform a state transition.
     *
     * @throws \DomainException if the transition is not allowed
     */
    public function transitionTo(ProjectState $newState): void
    {
        if ($this->state->toString() === $newState->toString()) {
            return; // no-op for same state
        }

        if (!$this->state->canTransitionTo($newState)) {
            throw new \DomainException(
                "Cannot transition project from '{$this->state->toString()}' to '{$newState->toString()}'"
            );
        }

        $this->state = $newState;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(
        string $name, 
        string $status, 
        ?\DateTimeImmutable $startDate, 
        ?\DateTimeImmutable $endDate, 
        array $malleableData
    ): void {
        $this->name = $name;

        // Validate state transition
        $newState = ProjectState::fromString($status);
        $this->transitionTo($newState);

        // Guard: intake projects must not have start_date
        if ($this->state->toString() === ProjectState::INTAKE && $startDate !== null) {
            throw new \DomainException('Cannot set start_date on a project in intake state');
        }

        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->malleableData = $malleableData;
    }

    public function archive(): void
    {
        $this->archivedAt = new \DateTimeImmutable();
    }
}
