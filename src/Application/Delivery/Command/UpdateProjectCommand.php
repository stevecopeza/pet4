<?php

declare(strict_types=1);

namespace Pet\Application\Delivery\Command;

class UpdateProjectCommand
{
    private int $id;
    private string $name;
    private string $status;
    private ?\DateTimeImmutable $startDate;
    private ?\DateTimeImmutable $endDate;
    private array $malleableData;

    public function __construct(
        int $id,
        string $name,
        string $status,
        ?\DateTimeImmutable $startDate = null,
        ?\DateTimeImmutable $endDate = null,
        array $malleableData = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->status = $status;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->malleableData = $malleableData;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function startDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }
}
