<?php

declare(strict_types=1);

namespace Pet\Domain\Identity\Entity;

class ContactAffiliation
{
    private int $customerId;
    private ?int $siteId;
    private ?string $role;
    private bool $isPrimary;

    public function __construct(
        int $customerId,
        ?int $siteId = null,
        ?string $role = null,
        bool $isPrimary = false
    ) {
        $this->customerId = $customerId;
        $this->siteId = $siteId;
        $this->role = $role;
        $this->isPrimary = $isPrimary;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function siteId(): ?int
    {
        return $this->siteId;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }
}
