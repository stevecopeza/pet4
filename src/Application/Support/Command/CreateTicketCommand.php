<?php

declare(strict_types=1);

namespace Pet\Application\Support\Command;

class CreateTicketCommand
{
    private int $customerId;
    private ?int $siteId;
    private ?int $slaId;
    private string $subject;
    private string $description;
    private string $priority;
    private array $malleableData;

    public function __construct(
        int $customerId,
        ?int $siteId,
        ?int $slaId,
        string $subject,
        string $description,
        string $priority = 'medium',
        array $malleableData = []
    ) {
        $this->customerId = $customerId;
        $this->siteId = $siteId;
        $this->slaId = $slaId;
        $this->subject = $subject;
        $this->description = $description;
        $this->priority = $priority;
        $this->malleableData = $malleableData;
    }

    public function customerId(): int
    {
        return $this->customerId;
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

    public function malleableData(): array
    {
        return $this->malleableData;
    }
}
