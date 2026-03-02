<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class ProficiencyLevel
{
    private ?int $id;
    private int $levelNumber;
    private string $name;
    private string $definition;
    private ?\DateTimeImmutable $createdAt;

    public function __construct(
        int $levelNumber,
        string $name,
        string $definition,
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->levelNumber = $levelNumber;
        $this->name = $name;
        $this->definition = $definition;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function levelNumber(): int
    {
        return $this->levelNumber;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function definition(): string
    {
        return $this->definition;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
