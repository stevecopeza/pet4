<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class KpiDefinition
{
    private ?int $id;
    private string $name;
    private string $description;
    private string $defaultFrequency;
    private string $unit;
    private ?\DateTimeImmutable $createdAt;

    public function __construct(
        string $name,
        string $description,
        string $defaultFrequency = 'monthly',
        string $unit = '%',
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->defaultFrequency = $defaultFrequency;
        $this->unit = $unit;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function defaultFrequency(): string
    {
        return $this->defaultFrequency;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
