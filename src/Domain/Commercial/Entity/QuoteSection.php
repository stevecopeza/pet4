<?php

declare(strict_types=1);

namespace Pet\Domain\Commercial\Entity;

class QuoteSection
{
    private ?int $id;
    private int $quoteId;
    private string $name;
    private int $orderIndex;
    private bool $showTotalValue;
    private bool $showItemCount;
    private bool $showTotalHours;

    public function __construct(
        int $quoteId,
        string $name,
        int $orderIndex,
        bool $showTotalValue = true,
        bool $showItemCount = false,
        bool $showTotalHours = false,
        ?int $id = null
    ) {
        $this->quoteId = $quoteId;
        $this->name = $name;
        $this->orderIndex = $orderIndex;
        $this->showTotalValue = $showTotalValue;
        $this->showItemCount = $showItemCount;
        $this->showTotalHours = $showTotalHours;
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function quoteId(): int
    {
        return $this->quoteId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function orderIndex(): int
    {
        return $this->orderIndex;
    }

    public function showTotalValue(): bool
    {
        return $this->showTotalValue;
    }

    public function showItemCount(): bool
    {
        return $this->showItemCount;
    }

    public function showTotalHours(): bool
    {
        return $this->showTotalHours;
    }
}

