<?php

declare(strict_types=1);

namespace Pet\Application\Work\Command;

class UpdateKpiDefinitionCommand
{
    private int $id;
    private string $name;
    private string $description;
    private string $defaultFrequency;
    private string $unit;

    public function __construct(
        int $id,
        string $name,
        string $description,
        string $defaultFrequency,
        string $unit
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->defaultFrequency = $defaultFrequency;
        $this->unit = $unit;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function defaultFrequency(): string
    {
        return $this->defaultFrequency;
    }

    public function unit(): string
    {
        return $this->unit;
    }
}

