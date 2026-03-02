<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity\Component;

abstract class QuoteComponent
{
    protected ?int $id;
    protected string $type; // 'implementation', 'recurring', 'catalog'
    protected string $section;
    protected ?string $description;

    public function __construct(
        string $type,
        ?string $description = null,
        ?int $id = null,
        string $section = 'General'
    ) {
        $this->type = $type;
        $this->description = $description;
        $this->id = $id;
        $this->section = $section;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function section(): string
    {
        return $this->section;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    abstract public function sellValue(): float;
    abstract public function internalCost(): float;

    public function margin(): float
    {
        if ($this->sellValue() === 0.0) {
            return 0.0;
        }
        return $this->sellValue() - $this->internalCost();
    }
}
