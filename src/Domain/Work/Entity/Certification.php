<?php

declare(strict_types=1);

namespace Pet\Domain\Work\Entity;

class Certification
{
    private ?int $id;
    private string $name;
    private string $issuingBody;
    private int $expiryMonths;
    private string $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $name,
        string $issuingBody,
        int $expiryMonths = 0,
        string $status = 'active',
        ?int $id = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->name = $name;
        $this->issuingBody = $issuingBody;
        $this->expiryMonths = $expiryMonths;
        $this->status = $status;
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

    public function issuingBody(): string
    {
        return $this->issuingBody;
    }

    public function expiryMonths(): int
    {
        return $this->expiryMonths;
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
