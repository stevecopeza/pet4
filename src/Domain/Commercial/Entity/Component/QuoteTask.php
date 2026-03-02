<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

class QuoteTask
{
    private ?int $id;
    private string $title;
    private ?string $description;
    private float $durationHours;
    private int $roleId; // Reference to Role Catalog Item
    private float $baseInternalRate; // Snapshot
    private float $sellRate; // Snapshot

    public function __construct(
        string $title,
        float $durationHours,
        int $roleId,
        float $baseInternalRate,
        float $sellRate,
        ?string $description = null,
        ?int $id = null
    ) {
        $this->title = $title;
        $this->durationHours = $durationHours;
        $this->roleId = $roleId;
        $this->baseInternalRate = $baseInternalRate;
        $this->sellRate = $sellRate;
        $this->description = $description;
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function durationHours(): float
    {
        return $this->durationHours;
    }

    public function roleId(): int
    {
        return $this->roleId;
    }

    public function baseInternalRate(): float
    {
        return $this->baseInternalRate;
    }

    public function sellRate(): float
    {
        return $this->sellRate;
    }

    public function sellValue(): float
    {
        return $this->durationHours * $this->sellRate;
    }

    public function internalCost(): float
    {
        return $this->durationHours * $this->baseInternalRate;
    }
}
