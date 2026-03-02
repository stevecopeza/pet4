<?php

declare(strict_types=1);

namespace Pet\Domain\Configuration\Entity;

class Setting
{
    private string $key;
    private string $value;
    private string $type; // string, integer, boolean, json
    private string $description;
    private ?\DateTimeImmutable $updatedAt;

    public function __construct(
        string $key,
        string $value,
        string $type = 'string',
        string $description = '',
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->key = $key;
        $this->value = $value;
        $this->type = $type;
        $this->description = $description;
        $this->updatedAt = $updatedAt;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
