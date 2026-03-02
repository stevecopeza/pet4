<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class AssignCertificationToPersonCommand
{
    private int $employeeId;
    private int $certificationId;
    private \DateTimeImmutable $obtainedDate;
    private ?\DateTimeImmutable $expiryDate;
    private ?string $evidenceUrl;

    public function __construct(
        int $employeeId,
        int $certificationId,
        string $obtainedDate,
        ?string $expiryDate = null,
        ?string $evidenceUrl = null
    ) {
        $this->employeeId = $employeeId;
        $this->certificationId = $certificationId;
        $this->obtainedDate = new \DateTimeImmutable($obtainedDate);
        $this->expiryDate = $expiryDate ? new \DateTimeImmutable($expiryDate) : null;
        $this->evidenceUrl = $evidenceUrl;
    }

    public function employeeId(): int
    {
        return $this->employeeId;
    }

    public function certificationId(): int
    {
        return $this->certificationId;
    }

    public function obtainedDate(): \DateTimeImmutable
    {
        return $this->obtainedDate;
    }

    public function expiryDate(): ?\DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function evidenceUrl(): ?string
    {
        return $this->evidenceUrl;
    }
}
