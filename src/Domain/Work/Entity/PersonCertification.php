<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class PersonCertification
{
    private ?int $id;
    private int $employeeId;
    private int $certificationId;
    private \DateTimeImmutable $obtainedDate;
    private ?\DateTimeImmutable $expiryDate;
    private ?string $evidenceUrl;
    private string $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        int $employeeId,
        int $certificationId,
        \DateTimeImmutable $obtainedDate,
        ?\DateTimeImmutable $expiryDate = null,
        ?string $evidenceUrl = null,
        string $status = 'valid',
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->employeeId = $employeeId;
        $this->certificationId = $certificationId;
        $this->obtainedDate = $obtainedDate;
        $this->expiryDate = $expiryDate;
        $this->evidenceUrl = $evidenceUrl;
        $this->status = $status;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
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

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
