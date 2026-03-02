<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

class UpdateCustomerCommand
{
    private int $id;
    private string $name;
    private ?string $legalName;
    private string $contactEmail;
    private string $status;
    private array $malleableData;

    public function __construct(
        int $id,
        string $name,
        string $contactEmail,
        ?string $legalName = null,
        string $status = 'active',
        array $malleableData = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->contactEmail = $contactEmail;
        $this->legalName = $legalName;
        $this->status = $status;
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

    public function legalName(): ?string
    {
        return $this->legalName;
    }

    public function contactEmail(): string
    {
        return $this->contactEmail;
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
