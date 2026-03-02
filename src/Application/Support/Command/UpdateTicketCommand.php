<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

class UpdateTicketCommand
{
    private int $id;
    private ?int $siteId;
    private ?int $slaId;
    private string $subject;
    private string $description;
    private string $priority;
    private string $status;
    private array $malleableData;

    public function __construct(
        int $id,
        ?int $siteId,
        ?int $slaId,
        string $subject,
        string $description,
        string $priority,
        string $status,
        array $malleableData = []
    ) {
        $this->id = $id;
        $this->siteId = $siteId;
        $this->slaId = $slaId;
        $this->subject = $subject;
        $this->description = $description;
        $this->priority = $priority;
        $this->status = $status;
        $this->malleableData = $malleableData;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function siteId(): ?int
    {
        return $this->siteId;
    }

    public function slaId(): ?int
    {
        return $this->slaId;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function priority(): string
    {
        return $this->priority;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function malleableData(): array
    {
        return $this->malleableData;
    }
}
