<?php

declare(strict_types=1);

namespace Pet\Application\Identity\Command;

class CreateSiteCommand
{
    private int $customerId;
    private string $name;
    private ?string $addressLines;
    private ?string $city;
    private ?string $state;
    private ?string $postalCode;
    private ?string $country;
    private string $status;
    private array $malleableData;

    public function __construct(
        int $customerId,
        string $name,
        ?string $addressLines = null,
        ?string $city = null,
        ?string $state = null,
        ?string $postalCode = null,
        ?string $country = null,
        string $status = 'active',
        array $malleableData = []
    ) {
        $this->customerId = $customerId;
        $this->name = $name;
        $this->addressLines = $addressLines;
        $this->city = $city;
        $this->state = $state;
        $this->postalCode = $postalCode;
        $this->country = $country;
        $this->status = $status;
        $this->malleableData = $malleableData;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function addressLines(): ?string
    {
        return $this->addressLines;
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function state(): ?string
    {
        return $this->state;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    public function country(): ?string
    {
        return $this->country;
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
