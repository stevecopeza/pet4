<?php

declare(strict_types=1);

namespace Pet\Application\Commercial\Command;

/**
 * Command to create a project ticket from an accepted quote task.
 * All sold/baseline fields are set at construction and are immutable once persisted.
 */
class CreateProjectTicketCommand
{
    private int $customerId;
    private ?int $projectId;
    private int $quoteId;
    private string $subject;
    private string $description;
    private int $soldMinutes;
    private int $soldValueCents;
    private int $estimatedMinutes;
    private ?int $phaseId;
    private ?int $requiredRoleId;
    private ?int $departmentIdExt;
    private ?int $changeOrderSourceTicketId;

    public function __construct(
        int $customerId,
        ?int $projectId,
        int $quoteId,
        string $subject,
        string $description,
        int $soldMinutes,
        int $soldValueCents,
        int $estimatedMinutes,
        ?int $phaseId = null,
        ?int $requiredRoleId = null,
        ?int $departmentIdExt = null,
        ?int $changeOrderSourceTicketId = null
    ) {
        $this->customerId = $customerId;
        $this->projectId = $projectId;
        $this->quoteId = $quoteId;
        $this->subject = $subject;
        $this->description = $description;
        $this->soldMinutes = $soldMinutes;
        $this->soldValueCents = $soldValueCents;
        $this->estimatedMinutes = $estimatedMinutes;
        $this->phaseId = $phaseId;
        $this->requiredRoleId = $requiredRoleId;
        $this->departmentIdExt = $departmentIdExt;
        $this->changeOrderSourceTicketId = $changeOrderSourceTicketId;
    }

    public function customerId(): int { return $this->customerId; }
    public function projectId(): ?int { return $this->projectId; }
    public function quoteId(): int { return $this->quoteId; }
    public function subject(): string { return $this->subject; }
    public function description(): string { return $this->description; }
    public function soldMinutes(): int { return $this->soldMinutes; }
    public function soldValueCents(): int { return $this->soldValueCents; }
    public function estimatedMinutes(): int { return $this->estimatedMinutes; }
    public function phaseId(): ?int { return $this->phaseId; }
    public function requiredRoleId(): ?int { return $this->requiredRoleId; }
    public function departmentIdExt(): ?int { return $this->departmentIdExt; }
    public function changeOrderSourceTicketId(): ?int { return $this->changeOrderSourceTicketId; }
}
